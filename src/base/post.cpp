#pragma once
#include "fibjs.h"
#include "object.h"
#include "Buffer.h"
#include "ifs/dmc.h"
#include "ifs/http.h"

#include <appbase/application.hpp>
#include <fc/exception/exception.hpp>
#include <fc/reflect/reflect.hpp>
#include <fc/io/json.hpp>
#include <fc/reflect/variant.hpp>

#define private public
#include <eosio/http_plugin/http_plugin.hpp>
#undef private

namespace eosio {
namespace detail {
    using std::string;
    struct abstract_conn {
        virtual ~abstract_conn() {};
        virtual bool verify_max_bytes_in_flight()
        {
            return true;
        };
        virtual bool verify_max_requests_in_flight()
        {
            return true;
        };
        virtual void handle_exception() {};

        virtual void send_response(std::optional<std::string> body, int code) {};
    };

    using abstract_conn_ptr = std::shared_ptr<abstract_conn>;
    using internal_url_handler = std::function<void(abstract_conn_ptr, string, string, url_response_callback)>;
}; // namespace detail

class http_plugin_impl : public std::enable_shared_from_this<http_plugin_impl> {
public:
    map<string, detail::internal_url_handler> url_handlers;
};
} // namespace eosio

using namespace eosio;

namespace fibjs {

result_t dmc_base::post(exlib::string resource, exlib::string body, exlib::string& retVal, AsyncEvent* ac)
{
    if (ac->isSync())
        return CHECK_ERROR(CALL_E_NOSYNC);

    app().post(appbase::priority::low, [=, &retVal]() {
        auto url_handlers = app().get_plugin<http_plugin>().my->url_handlers;
        auto handler_itr = url_handlers.find(resource);

        if (handler_itr == url_handlers.end()) {
            ac->post(CALL_E_INVALIDARG);
        } else {
            detail::internal_url_handler* hdlr = new detail::internal_url_handler(handler_itr->second);
            auto _conn = std::make_shared<detail::abstract_conn>();
            try {
                (*hdlr)(_conn, std::move(resource), std::move(body),
                    [=, &retVal](int code, std::optional<fc::variant> result) {
                        std::string json = fc::json::to_string(result, fc::time_point::maximum());
                        retVal = json;
                        ac->post(0);
                        delete hdlr;
                    });
            } catch (...) {
                ac->post(CALL_E_INTERNAL);
                delete hdlr;
            }
        }
    });

    return CALL_E_PENDDING;
}
} // namespace fibjs
