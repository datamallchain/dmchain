#pragma once

#include <v8.h>
#include <fc/variant.hpp>
#include <eosio/chain/name.hpp>
#include "object.h"

namespace eosio {
namespace chain {
    fibjs::JSValue js_value(fibjs::Isolate* isolate, const fc::variant& var);

    inline fibjs::result_t string_to_name(const exlib::string str, uint64_t& retVal)
    {
        int32_t len = (int32_t)str.length();

        if (len > 13)
            return CHECK_ERROR(fibjs::Runtime::setError("Name is longer than 13 characters (" + str + ")."));

        eosio::chain::name _name = eosio::chain::string_to_name(str.c_str());

        exlib::string str1 = _name.to_string();
        if (str != str1)
            return CHECK_ERROR(fibjs::Runtime::setError("Name not properly normalized (name: " + str + ", normalized: " + str + ")."));

        retVal = _name.to_uint64_t();

        return 0;
    }

    inline fibjs::result_t string_to_name(fibjs::JSValue v, uint64_t& retVal)
    {
        fibjs::result_t hr;
        exlib::string str;

        hr = fibjs::GetArgumentValue(v, str);
        if (hr < 0)
            return hr;

        return string_to_name(str, retVal);
    }
} // namespace chain
} // namespace eosio
