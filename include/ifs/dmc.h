/***************************************************************************
 *                                                                         *
 *   This file was automatically generated using idlc.js                   *
 *   PLEASE DO NOT EDIT!!!!                                                *
 *                                                                         *
 ***************************************************************************/

#pragma once

/**
 @author Leo Hoo <lion@9465.net>
 */

#include "../object.h"
#include "ifs/EventEmitter.h"

namespace fibjs {

class EventEmitter_base;

class dmc_base : public EventEmitter_base {
    DECLARE_CLASS(dmc_base);

public:
    // dmc_base
    static result_t get_data_dir(exlib::string& retVal);
    static result_t set_data_dir(exlib::string newVal);
    static result_t get_config_dir(exlib::string& retVal);
    static result_t set_config_dir(exlib::string newVal);
    static result_t get_pubkey_prefix(exlib::string& retVal);
    static result_t set_pubkey_prefix(exlib::string newVal);
    static result_t load(exlib::string name, v8::Local<v8::Object> cfg);
    static result_t load(v8::Local<v8::Object> cfgs);
    static result_t start();
    static result_t stop();
    static result_t post(exlib::string resource, exlib::string body, exlib::string& retVal, AsyncEvent* ac);

public:
    static void s__new(const v8::FunctionCallbackInfo<v8::Value>& args)
    {
        CONSTRUCT_INIT();

        Isolate* isolate = Isolate::current();

        isolate->m_isolate->ThrowException(
            isolate->NewString("not a constructor"));
    }

public:
    static void s_static_get_data_dir(v8::Local<v8::Name> property, const v8::PropertyCallbackInfo<v8::Value>& args);
    static void s_static_set_data_dir(v8::Local<v8::Name> property, v8::Local<v8::Value> value, const v8::PropertyCallbackInfo<void>& args);
    static void s_static_get_config_dir(v8::Local<v8::Name> property, const v8::PropertyCallbackInfo<v8::Value>& args);
    static void s_static_set_config_dir(v8::Local<v8::Name> property, v8::Local<v8::Value> value, const v8::PropertyCallbackInfo<void>& args);
    static void s_static_get_pubkey_prefix(v8::Local<v8::Name> property, const v8::PropertyCallbackInfo<v8::Value>& args);
    static void s_static_set_pubkey_prefix(v8::Local<v8::Name> property, v8::Local<v8::Value> value, const v8::PropertyCallbackInfo<void>& args);
    static void s_static_load(const v8::FunctionCallbackInfo<v8::Value>& args);
    static void s_static_start(const v8::FunctionCallbackInfo<v8::Value>& args);
    static void s_static_stop(const v8::FunctionCallbackInfo<v8::Value>& args);
    static void s_static_post(const v8::FunctionCallbackInfo<v8::Value>& args);

public:
    ASYNC_STATICVALUE3(dmc_base, post, exlib::string, exlib::string, exlib::string);
};
}

namespace fibjs {
inline ClassInfo& dmc_base::class_info()
{
    static ClassData::ClassMethod s_method[] = {
        { "load", s_static_load, true },
        { "start", s_static_start, true },
        { "stop", s_static_stop, true },
        { "post", s_static_post, true },
        { "postSync", s_static_post, true }
    };

    static ClassData::ClassProperty s_property[] = {
        { "data_dir", s_static_get_data_dir, s_static_set_data_dir, true },
        { "config_dir", s_static_get_config_dir, s_static_set_config_dir, true },
        { "pubkey_prefix", s_static_get_pubkey_prefix, s_static_set_pubkey_prefix, true }
    };

    static ClassData s_cd = {
        "dmc", true, s__new, NULL,
        ARRAYSIZE(s_method), s_method, 0, NULL, ARRAYSIZE(s_property), s_property, 0, NULL, NULL, NULL,
        &EventEmitter_base::class_info()
    };

    static ClassInfo s_ci(s_cd);
    return s_ci;
}

inline void dmc_base::s_static_get_data_dir(v8::Local<v8::Name> property, const v8::PropertyCallbackInfo<v8::Value>& args)
{
    exlib::string vr;

    METHOD_NAME("dmc.data_dir");
    PROPERTY_ENTER();

    hr = get_data_dir(vr);

    METHOD_RETURN();
}

inline void dmc_base::s_static_set_data_dir(v8::Local<v8::Name> property, v8::Local<v8::Value> value, const v8::PropertyCallbackInfo<void>& args)
{
    METHOD_NAME("dmc.data_dir");
    PROPERTY_ENTER();
    PROPERTY_VAL(exlib::string);

    hr = set_data_dir(v0);

    PROPERTY_SET_LEAVE();
}

inline void dmc_base::s_static_get_config_dir(v8::Local<v8::Name> property, const v8::PropertyCallbackInfo<v8::Value>& args)
{
    exlib::string vr;

    METHOD_NAME("dmc.config_dir");
    PROPERTY_ENTER();

    hr = get_config_dir(vr);

    METHOD_RETURN();
}

inline void dmc_base::s_static_set_config_dir(v8::Local<v8::Name> property, v8::Local<v8::Value> value, const v8::PropertyCallbackInfo<void>& args)
{
    METHOD_NAME("dmc.config_dir");
    PROPERTY_ENTER();
    PROPERTY_VAL(exlib::string);

    hr = set_config_dir(v0);

    PROPERTY_SET_LEAVE();
}

inline void dmc_base::s_static_get_pubkey_prefix(v8::Local<v8::Name> property, const v8::PropertyCallbackInfo<v8::Value>& args)
{
    exlib::string vr;

    METHOD_NAME("dmc.pubkey_prefix");
    PROPERTY_ENTER();

    hr = get_pubkey_prefix(vr);

    METHOD_RETURN();
}

inline void dmc_base::s_static_set_pubkey_prefix(v8::Local<v8::Name> property, v8::Local<v8::Value> value, const v8::PropertyCallbackInfo<void>& args)
{
    METHOD_NAME("dmc.pubkey_prefix");
    PROPERTY_ENTER();
    PROPERTY_VAL(exlib::string);

    hr = set_pubkey_prefix(v0);

    PROPERTY_SET_LEAVE();
}

inline void dmc_base::s_static_load(const v8::FunctionCallbackInfo<v8::Value>& args)
{
    METHOD_NAME("dmc.load");
    METHOD_ENTER();

    METHOD_OVER(2, 1);

    ARG(exlib::string, 0);
    OPT_ARG(v8::Local<v8::Object>, 1, v8::Object::New(isolate));

    hr = load(v0, v1);

    METHOD_OVER(1, 1);

    ARG(v8::Local<v8::Object>, 0);

    hr = load(v0);

    METHOD_VOID();
}

inline void dmc_base::s_static_start(const v8::FunctionCallbackInfo<v8::Value>& args)
{
    METHOD_NAME("dmc.start");
    METHOD_ENTER();

    METHOD_OVER(0, 0);

    hr = start();

    METHOD_VOID();
}

inline void dmc_base::s_static_stop(const v8::FunctionCallbackInfo<v8::Value>& args)
{
    METHOD_NAME("dmc.stop");
    METHOD_ENTER();

    METHOD_OVER(0, 0);

    hr = stop();

    METHOD_VOID();
}

inline void dmc_base::s_static_post(const v8::FunctionCallbackInfo<v8::Value>& args)
{
    exlib::string vr;

    METHOD_NAME("dmc.post");
    METHOD_ENTER();

    ASYNC_METHOD_OVER(2, 1);

    ARG(exlib::string, 0);
    OPT_ARG(exlib::string, 1, "");

    if (!cb.IsEmpty())
        hr = acb_post(v0, v1, cb, args);
    else
        hr = ac_post(v0, v1, vr);

    METHOD_RETURN();
}
}
