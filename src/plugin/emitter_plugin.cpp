#include "fibjs.h"
#include "object.h"
#include "emitter_plugin.h"

#include <atomic>
#include <boost/signals2/connection.hpp>

#include <eosio/chain/exceptions.hpp>
#include <eosio/chain_plugin/chain_plugin.hpp>

#include "Fiber.h"
#include "EventEmitter.h"
#include "types.h"
#include "js_value.h"

#include "ifs/dmc.h"

namespace fibjs {
extern exlib::LockedList<Isolate> s_isolates;
} // namespace fibjs

using namespace fibjs;
using namespace eosio::chain;

namespace eosio {
std::atomic<int64_t> emitter_plugin::emitted_id;
std::atomic<int64_t> emitter_plugin::mem_id;
std::atomic_bool emitter_plugin::g_js_error;
std::queue<event_queue_object> emitter_plugin::event_cache_queue;
exlib::spinlock emitter_plugin::m_queue_mutex;
exlib::Semaphore emitter_plugin::s_emitter;
exlib::OSSemaphore* emitter_plugin::s_emitter_db;
uint32_t s_last_irreversible_block;

struct base_event_cache_object : public chainbase::object<event_cache_object_type, base_event_cache_object> {
    OBJECT_CTOR(base_event_cache_object, (data));

    id_type id;
    int64_t pid;
    uint8_t event;
    shared_blob data;
};

using event_cache_id_type = base_event_cache_object::id_type;

struct by_pid;
using event_cache_index = chainbase::shared_multi_index_container<
    base_event_cache_object,
    indexed_by<ordered_unique<tag<by_id>, member<base_event_cache_object, base_event_cache_object::id_type, &base_event_cache_object::id>>,
        ordered_unique<tag<by_pid>, member<base_event_cache_object, int64_t, &base_event_cache_object::pid>>>>;
} // namespace eosio

CHAINBASE_SET_INDEX_TYPE(eosio::base_event_cache_object, eosio::event_cache_index)
FC_REFLECT(eosio::base_event_cache_object, (id)(pid)(event)(data))

namespace eosio {
using namespace chain;

const static uint64_t MAX_DB_SIZE = 30000;
static emitter_plugin& _emitter_plugin = app().register_plugin<emitter_plugin>();

class emitter_plugin_impl {
public:
    emitter_plugin_impl()
    {
    }

public:
    chain_plugin* chain_plug = nullptr;
    std::optional<boost::signals2::scoped_connection> accepted_block_connection;
    std::optional<boost::signals2::scoped_connection> applied_transaction_connection;

public:
    void applied_event(const uint8_t& event, const signed_block& data)
    {
        try {
            auto blk = fc::raw::pack(data);

            stash_data(event, blk);
        } catch (std::exception& e) {
            elog("STD Exception while applied_${event}: ${e}", ("event", event)("e", e.what()));
            app().quit();
        } catch (fc::exception& e) {
            elog("STD Exception while applied_${event}: ${e}", ("event", event)("e", e.to_detail_string()));
            app().quit();
        } catch (...) {
            elog("Unknown exception while applied_${event}", ("event", event));
            app().quit();
        }
    }

    void applied_event(const uint8_t& event, const chain::transaction_trace_ptr& data)
    {
        try {
            const auto abi_serializer_max_time = chain_plug->get_abi_serializer_max_time();
            auto& chain = chain_plug->chain();

            fc::variant trans = chain.to_variant_with_abi(data, abi_serializer::create_yield_function(abi_serializer_max_time));
            auto trx = fc::raw::pack(trans);

            stash_data(event, trx);
        } catch (std::exception& e) {
            elog("STD Exception while applied_${event}: ${e}", ("event", event)("e", e.what()));
            app().quit();
        } catch (fc::exception& e) {
            elog("STD Exception while applied_${event}: ${e}", ("event", event)("e", e.to_detail_string()));
            app().quit();
        } catch (...) {
            elog("Unknown exception while applied_${event}", ("event", event));
            app().quit();
        }
    }

    void stash_data(const uint8_t& event, const std::vector<char>& data)
    {
        _emitter_plugin.s_emitter_db->Wait();

        chainbase::database& db = const_cast<chainbase::database&>(chain_plug->chain().db());
        auto& idx = db.get_index<event_cache_index, by_pid>();
        auto& mutable_idx = db.get_mutable_index<event_cache_index>();

        for (auto itr = idx.begin(); itr != idx.end() && itr->pid <= _emitter_plugin.emitted_id; ++itr)
            mutable_idx.remove(*itr);

        db.create<base_event_cache_object>([&](auto& obj) {
            obj.event = event;
            obj.pid = ++_emitter_plugin.mem_id;
            obj.data.assign(data.data(), data.size());
        });

        _emitter_plugin.m_queue_mutex.lock();
        _emitter_plugin.event_cache_queue.emplace(_emitter_plugin.mem_id, event, data);
        _emitter_plugin.m_queue_mutex.unlock();

        _emitter_plugin.s_emitter.post();
    }

    static result_t js_handler(Isolate* isolate)
    {
        while (!app().is_quiting()) {
            if (!_emitter_plugin.s_emitter.trywait()) {
                v8::Unlocker unlocker(isolate->m_isolate);

                isolate->Unref();
                _emitter_plugin.s_emitter.wait();
                isolate->Ref();
            }

            _emitter_plugin.m_queue_mutex.lock();
            event_queue_object obj = _emitter_plugin.event_cache_queue.front();
            _emitter_plugin.event_cache_queue.pop();
            _emitter_plugin.m_queue_mutex.unlock();

            JSFiber::EnterJsScope s;
            v8::HandleScope handle_scope(isolate->m_isolate);
            v8::Local<v8::Value> v;

            JSTrigger t(isolate->m_isolate, dmc_base::class_info().getModule(isolate));
            bool r = false;

            exlib::string event = "";
            switch (obj.event) {
            case irreversible_block_type:
                event = "irreversible_block";
                v = js_object(isolate, obj.data);

                break;
            case block_type:
                event = "block";
                v = js_object(isolate, obj.data);

                break;
            case transaction_type:
                event = "transaction";
                v = chain::js_value(isolate, fc::raw::unpack<fc::variant>(obj.data));
                break;
            }

            result_t hr = t._emit(event, &v, 1, r);
            if (hr < 0) {
                _emitter_plugin.g_js_error = true;
                app().quit();
                break;
            }

            {
                _emitter_plugin.m_queue_mutex.lock();
                _emitter_plugin.emitted_id = obj.id;
                _emitter_plugin.m_queue_mutex.unlock();
            }
            _emitter_plugin.s_emitter_db->Post();
        }
        return 0;
    }

    static v8::Local<v8::Value> js_object(fibjs::Isolate* isolate, const std::vector<char>& var)
    {
        v8::Local<v8::Object> obj;
        try {
            auto block = fc::raw::unpack<signed_block>(var);

            v8::Local<v8::Context> context = isolate->context();
            obj = v8::Object::New(isolate->m_isolate);
            obj->Set(context, isolate->NewString("block_num"), v8::BigInt::New(isolate->m_isolate, block.block_num()));
            obj->Set(context, isolate->NewString("id"), isolate->NewString(block.calculate_id().str()));

            v8::Local<v8::Object> block_obj = v8::Object::New(isolate->m_isolate);
            block_obj->Set(context, isolate->NewString("timestamp"), isolate->NewString(fc::time_point_sec(block.timestamp).to_iso_string()));
            block_obj->Set(context, isolate->NewString("producer"), isolate->NewString(block.producer.to_string()));
            block_obj->Set(context, isolate->NewString("previous"), isolate->NewString(block.previous.str()));

            obj->Set(context, isolate->NewString("block"), block_obj);
        } catch (std::exception& e) {
            elog("STD Exception: ${e}", ("e", e.what()));
        } catch (fc::exception& e) {
            elog("FC Exception: ${e}", ("e", e.to_detail_string()));
        } catch (...) {
            elog("Unknown exception when call js_object()");
        }

        return obj;
    }

    void on_accepted_block(const chain::block_state_ptr& bs)
    {
        auto& chain = chain_plug->chain();

        if (bs->block_num == 2)
            applied_event(block_type, *chain.fetch_block_by_number(1));
        applied_event(block_type, *bs->block);

        uint32_t _last_irreversible_block = chain.last_irreversible_block_num();
        while (s_last_irreversible_block < _last_irreversible_block) {
            auto irrbs = chain.fetch_block_by_number(++s_last_irreversible_block);
            if (irrbs)
                applied_event(irreversible_block_type, *irrbs);
        }
    }

    void on_applied_transaction(const chain::transaction_trace_ptr& trace)
    {
        if (trace->action_traces.size() != 1 || trace->action_traces[0].act.account != "eosio"_n || trace->action_traces[0].act.name != "onblock"_n) {
            applied_event(transaction_type, trace);
        }
    }
};

emitter_plugin::emitter_plugin()
    : my(new emitter_plugin_impl())
{
}

emitter_plugin::~emitter_plugin()
{
}

void emitter_plugin::set_program_options(options_description&, options_description& cfg)
{
}

void emitter_plugin::plugin_initialize(const variables_map& options)
{
    try {
        my->chain_plug = app().find_plugin<chain_plugin>();
        EOS_ASSERT(my->chain_plug, chain::missing_chain_plugin_exception, "");
        auto& chain = my->chain_plug->chain();

        chainbase::database& db = const_cast<chainbase::database&>(chain.db());
        db.add_index<event_cache_index>();
        auto& idx = db.get_index<event_cache_index, by_pid>();

        int s_size = MAX_DB_SIZE > idx.size() ? MAX_DB_SIZE - idx.size() : 0;
        _emitter_plugin.g_js_error = false;
        _emitter_plugin.s_emitter_db = new exlib::OSSemaphore(s_size);

        auto itr = idx.begin();
        if (itr != idx.end()) {
            _emitter_plugin.emitted_id = itr->pid - 1;
            _emitter_plugin.mem_id = idx.rbegin()->pid;

            while (itr != idx.end()) {
                _emitter_plugin.event_cache_queue.emplace(itr->pid, itr->event, itr->data);
                _emitter_plugin.s_emitter.post();
                ++itr;
            }
        } else {
            _emitter_plugin.emitted_id = -1;
            _emitter_plugin.mem_id = -1;
        }
        
        Isolate* isolate = s_isolates.head();
        syncCall(isolate, my->js_handler, isolate);

        my->accepted_block_connection.emplace(chain.accepted_block.connect([&](const chain::block_state_ptr& bs) {
            my->on_accepted_block(bs);
        }));
        my->applied_transaction_connection.emplace(chain.applied_transaction.connect([&](std::tuple<const chain::transaction_trace_ptr&, const chain::packed_transaction_ptr&> t) {
            my->on_applied_transaction(std::get<0>(t));
        }));
    }
    FC_LOG_AND_RETHROW()
}

void emitter_plugin::plugin_startup()
{
    s_last_irreversible_block = my->chain_plug->chain().last_irreversible_block_num();
}

void emitter_plugin::plugin_shutdown()
{
    my->accepted_block_connection.reset();
    my->applied_transaction_connection.reset();
}

} // namespace eosio