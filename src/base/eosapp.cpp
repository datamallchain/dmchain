#define public_key_legacy_prefix public_key_legacy_prefix_eos
#include <fc/crypto/public_key.hpp>
#undef public_key_legacy_prefix

#include "v8/src/flags/flags.h"
#include "fibjs.h"
#include "object.h"
#include "Fiber.h"
#include "console.h"
#include "SandBox.h"
#include "EventEmitter.h"

#include <appbase/application.hpp>
#include <appbase/plugin.hpp>

#include <eosio/blockvault_client_plugin/blockvault_client_plugin.hpp>
#include <eosio/chain_api_plugin/chain_api_plugin.hpp>
#include <eosio/chain_plugin/chain_plugin.hpp>
#include <eosio/db_size_api_plugin/db_size_api_plugin.hpp>
#include <eosio/history_api_plugin/history_api_plugin.hpp>
#include <eosio/history_plugin/history_plugin.hpp>
#include <eosio/http_client_plugin/http_client_plugin.hpp>
#include <eosio/http_plugin/http_plugin.hpp>
#include <eosio/login_plugin/login_plugin.hpp>
#include <eosio/net_api_plugin/net_api_plugin.hpp>
#include <eosio/net_plugin/net_plugin.hpp>
#include <eosio/producer_api_plugin/producer_api_plugin.hpp>
#include <eosio/producer_plugin/producer_plugin.hpp>
#include <eosio/resource_monitor_plugin/resource_monitor_plugin.hpp>
#include <eosio/signature_provider_plugin/signature_provider_plugin.hpp>
#include <eosio/state_history_plugin/state_history_plugin.hpp>
#include <eosio/trace_api/trace_api_plugin.hpp>
#include <eosio/txn_test_gen_plugin/txn_test_gen_plugin.hpp>
#include <eosio/wallet_api_plugin/wallet_api_plugin.hpp>
#include <eosio/wallet_plugin/wallet_plugin.hpp>

#include <fc/log/logger_config.hpp>
#include <fc/log/appender.hpp>
#include <fc/exception/exception.hpp>

#include "ifs/dmc.h"
#include "emitter_plugin.h"
#include "gitinfo.h"

// only for config.hpp
using namespace std;
#include <nodeos/config.hpp>

using namespace appbase;
using namespace eosio;

namespace fc {
namespace crypto {
    namespace config {
        // public key prefix
        const char* public_key_legacy_prefix = "DM";
    };
};
};

namespace appbase {
const char* appbase_version_string = "v" GIT_INFO;
} // namespace appbase

namespace fibjs {

extern obj_ptr<NObject> g_info;
extern exlib::string appname;

DECLARE_MODULE(dmc);

enum return_codes {
    OTHER_FAIL = -2,
    INITIALIZE_FAIL = -1,
    SUCCESS = 0,
    BAD_ALLOC = 1,
    DATABASE_DIRTY = 2,
    FIXED_REVERSIBLE = 3,
    EXTRACTED_GENESIS = 4
};

static std::vector<exlib::string> s_args;
static bool s_state_started = false;
static bool s_state_stopped = false;

void init_eos()
{
    appname = "DMCHAIN V" GIT_INFO ". Based on fibjs";
    g_info->add("dmc", appbase_version_string);

    app().set_version(eosio::nodeos::config::version);

    app().register_plugin<chain_api_plugin>();
    app().register_plugin<chain_plugin>();
    app().register_plugin<db_size_api_plugin>();
    app().register_plugin<history_api_plugin>();
    app().register_plugin<history_plugin>();
    app().register_plugin<http_client_plugin>();
    app().register_plugin<http_plugin>();
    app().register_plugin<net_api_plugin>();
    app().register_plugin<net_plugin>();
    app().register_plugin<producer_api_plugin>();
    app().register_plugin<producer_plugin>();
    app().register_plugin<txn_test_gen_plugin>();
    app().register_plugin<wallet_api_plugin>();
    app().register_plugin<wallet_plugin>();

    app().register_plugin<blockvault_client_plugin>();
    app().register_plugin<login_plugin>();
    app().register_plugin<resource_monitor_plugin>();
    app().register_plugin<signature_provider_plugin>();
    app().register_plugin<state_history_plugin>();
    app().register_plugin<trace_api_plugin>();
    app().register_plugin<emitter_plugin>();

    auto root = fc::app_path();
    app().set_default_data_dir(root / "eosio/nodeos/data");
    app().set_default_config_dir(root / "eosio/nodeos/config");
    s_args.push_back("dmc");
}

static result_t emit_event(std::string event)
{
    Isolate* isolate = Isolate::current();

    JSFiber::EnterJsScope s;
    v8::HandleScope handle_scope(isolate->m_isolate);

    JSTrigger t(isolate->m_isolate, dmc_base::class_info().getModule(isolate));
    bool r = false;

    result_t hr = t._emit(event, NULL, 0, r);
    if (hr < 0) {
        elog("error occurs when emit ${event} event", ("event", event));
        app().quit();
    }
    return 0;
}

result_t dmc_base::load(exlib::string name, v8::Local<v8::Object> cfg)
{
    exlib::string plug_name = "eosio::" + name + "_plugin";
    auto plugin = app().find_plugin(plug_name.c_str());

    if (!plugin)
        return CHECK_ERROR(Runtime::setError("Plugin " + name + " not found."));

    Isolate* isolate = Isolate::current();
    v8::Local<v8::Context> context = isolate->context();

    if (plugin->get_state() == abstract_plugin::registered) {
        options_description cli, opt;

        plugin->set_program_options(cli, opt);

        s_args.push_back("--plugin");
        s_args.push_back(plug_name);

        JSArray ks = cfg->GetPropertyNames(context);

        int32_t len = ks->Length();
        int32_t i;
        result_t hr;

        for (i = 0; i < len; i++) {
            const char* argv[3] = { "dmc" };

            JSValue k = ks->Get(context, i);
            exlib::string key(isolate->toString(k));

            argv[1] = key.c_str();

            if (opt.find_nothrow(argv[1], true) == NULL && cli.find_nothrow(argv[1], true) == NULL)
                return CHECK_ERROR(Runtime::setError("unrecognised option: " + key));

            bool isMultiOpt = false;
            options_description _opt = (opt.find_nothrow(argv[1], true) == NULL) ? cli : opt;

            if (_opt.find_nothrow(argv[1], true)->semantic()->max_tokens() > 0) {
                JSValue kss = cfg->Get(context, k);
                if (kss->IsArray())
                    isMultiOpt = true;
            }

            if (!isMultiOpt) {
                s_args.push_back("--" + key);
                JSValue mk = cfg->Get(context, k);

                exlib::string value(isolate->toString(mk));
                if (value == "true")
                    continue;
                else if (value == "false")
                    s_args.pop_back();
                else
                    s_args.push_back(isolate->toString(mk));

            } else {
                JSValue sk = cfg->Get(context, k);
                const JSArray vals = JSArray::Cast(sk);

                if (vals->Length() == 0)
                    return CHECK_ERROR(Runtime::setError("empty option parameters: " + key));

                for (int32_t j = 0; j < vals->Length(); j++) {
                    s_args.push_back("--" + key);
                    JSValue sj = vals->Get(context, j);
                    s_args.push_back(isolate->toString(sj));
                }
            }
        }
    }

    return 0;
}

result_t dmc_base::load(v8::Local<v8::Object> cfgs)
{
    Isolate* isolate = Isolate::current();
    v8::Local<v8::Context> context = isolate->context();
    JSArray ks = cfgs->GetPropertyNames(context);
    int32_t len = ks->Length();
    int32_t i;
    result_t hr;

    for (i = 0; i < len; i++) {
        JSValue k = ks->Get(context, i);

        v8::Local<v8::Object> o;
        JSValue v = cfgs->Get(context, k);
        hr = GetArgumentValue(v, o, true);
        if (hr < 0)
            return hr;

        load(isolate->toString(k), o);
    }

    return 0;
}

result_t dmc_base::start()
{
    class _thread : public exlib::OSThread {
    public:
        _thread()
            : m_main(Isolate::current())
        {
            m_main->Ref();
        }

    public:
        virtual void Run()
        {
            try {

                std::vector<char*> s_argv;

                s_argv.resize(s_args.size());
                for (int32_t i = 0; i < (int32_t)s_args.size(); i++)
                    s_argv[i] = s_args[i].c_buffer();

                EOS_ASSERT(app().initialize<>(s_argv.size(), s_argv.data()), eosio::chain::chain_exception, "INITIALIZE_FAIL");
                app().startup();

                syncCall(m_main, emit_event, "load");

                app().exec();
            } catch (const fc::exception& e) {
                elog("${e}", ("e", e.to_detail_string()));
                syncCall(m_main, emit_event, "error");
            } catch (const chain_exception& e) {
                elog("${e}", ("e", e.to_detail_string()));
                syncCall(m_main, emit_event, "error");
            } catch (const boost::interprocess::bad_alloc& e) {
                elog("bad alloc");
                syncCall(m_main, emit_event, "error");
            } catch (const boost::exception& e) {
                elog("${e}", ("e", boost::diagnostic_information(e)));
                syncCall(m_main, emit_event, "error");
            } catch (const std::runtime_error& e) {
                if (std::string(e.what()) == "database dirty flag set") {
                    elog("database dirty flag set (likely due to unclean shutdown): replay required");
                } else if (std::string(e.what()) == "database metadata dirty flag set") {
                    elog("database metadata dirty flag set (likely due to unclean shutdown): replay required");
                } else {
                    elog("${e}", ("e", e.what()));
                }
                syncCall(m_main, emit_event, "error");
            } catch (const std::exception& e) {
                elog("${e}", ("e", e.what()));
                syncCall(m_main, emit_event, "error");
            } catch (...) {
                elog("unknown exception");
                syncCall(m_main, emit_event, "error");
            }
            syncCall(m_main, emit_event, "close");
            m_main->Unref();
        }

        static result_t init_proc(_thread* p)
        {
            p->start();
            return 0;
        }

    private:
        Isolate* m_main;
    };

    if (s_state_stopped)
        return CHECK_ERROR(Runtime::setError("non-reentrant function"));

    s_state_stopped = true;

    Isolate* m_isolate = new Isolate("");
    syncCall(m_isolate, _thread::init_proc, new _thread());
    return 0;
}

result_t dmc_base::get_data_dir(exlib::string& retVal)
{
    retVal = app().data_dir().c_str();
    return 0;
}

result_t dmc_base::set_data_dir(exlib::string newVal)
{
    app().set_default_data_dir(newVal.c_str());
    return 0;
}

result_t dmc_base::get_config_dir(exlib::string& retVal)
{
    retVal = app().config_dir().c_str();
    return 0;
}

result_t dmc_base::set_config_dir(exlib::string newVal)
{
    app().set_default_config_dir(newVal.c_str());
    return 0;
}

result_t dmc_base::get_pubkey_prefix(exlib::string& retVal)
{
    retVal = fc::crypto::config::public_key_legacy_prefix;
    return 0;
}

result_t dmc_base::set_pubkey_prefix(exlib::string newVal)
{
    static exlib::string s_dmc_root_key(newVal);

    fc::crypto::config::public_key_legacy_prefix = s_dmc_root_key.c_str();

    return 0;
}

result_t dmc_base::stop()
{
    if (s_state_started)
        return CHECK_ERROR(Runtime::setError("non-reentrant function"));

    s_state_started = true;
    app().quit();
    return 0;
}
} // namespace fibjs
