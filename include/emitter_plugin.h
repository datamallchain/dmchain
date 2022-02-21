#pragma once

#include <appbase/application.hpp>
#include <eosio/chain/types.hpp>
#include <eosio/chain_plugin/chain_plugin.hpp>

#include <atomic>

namespace eosio {
using namespace appbase;

struct event_queue_object {
    uint64_t id;
    uint8_t event;
    std::vector<char> data;

    event_queue_object()
    {
    }

    event_queue_object(const event_queue_object& obj)
    {
        operator=(obj);
    }

    event_queue_object(const uint64_t& _id, const uint8_t& _event, const chain::shared_blob& _data)
    {
        id = _id;
        event = _event;
        data.assign(_data.begin(), _data.end());
    }

    event_queue_object(const uint64_t& _id, const uint8_t& _event, const std::vector<char>& _data)
    {
        id = _id;
        event = _event;
        data.assign(_data.begin(), _data.end());
    }

    event_queue_object& operator=(const event_queue_object& obj)
    {
        id = obj.id;
        event = obj.event;
        data = obj.data;

        return *this;
    }
};

enum event_type {
    irreversible_block_type,
    block_type,
    transaction_type
};

using namespace chain;

/**
 *  This is a template plugin, intended to serve as a starting point for making new plugins
 */
class emitter_plugin : public appbase::plugin<emitter_plugin> {
public:
    emitter_plugin();
    virtual ~emitter_plugin();

    APPBASE_PLUGIN_REQUIRES((chain_plugin))
    virtual void set_program_options(options_description&, options_description& cfg) override;

    void plugin_initialize(const variables_map& options);
    void plugin_startup();
    void plugin_shutdown();

    static exlib::Semaphore s_emitter;
    static exlib::OSSemaphore* s_emitter_db;
    static std::atomic_bool g_js_error;
    static exlib::spinlock m_queue_mutex;
    static std::atomic<std::int64_t> emitted_id;
    static std::atomic<std::int64_t> mem_id;
    static std::queue<event_queue_object> event_cache_queue;

private:
    std::unique_ptr<class emitter_plugin_impl> my;
};

} // namespace eosio