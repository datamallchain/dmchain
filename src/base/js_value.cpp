#include "js_value.h"
#include "fibjs.h"
#include "Buffer.h"
#include "v8_api.h"
#include <fc/variant_object.hpp>

using namespace fibjs;

namespace eosio {
namespace chain {

    fibjs::JSValue js_value(Isolate* isolate, const fc::variant& var)
    {
        v8::Local<v8::Value> v;
        v8::Local<v8::Context> context = isolate->context();

        switch (var.get_type()) {
        case fc::variant::type_id::null_type:
            return v8::Null(isolate->m_isolate);
        case fc::variant::type_id::int64_type:
            return v8::BigInt::New(isolate->m_isolate, var.as_int64());
        case fc::variant::type_id::uint64_type:
            return v8::BigInt::NewFromUnsigned(isolate->m_isolate, var.as_uint64());
        case fc::variant::type_id::double_type:
            return v8::Number::New(isolate->m_isolate, var.as_double());
        case fc::variant::type_id::bool_type:
            return var.as_bool() ? v8::True(isolate->m_isolate) : v8::False(isolate->m_isolate);
        case fc::variant::type_id::string_type: {
            std::string str = var.as_string();
            return isolate->NewString(str.c_str(), str.length());
        }
        case fc::variant::type_id::array_type: {
            size_t sz = var.size();
            size_t i;
            v8::Local<v8::Array> arr = v8::Array::New(isolate->m_isolate, (int32_t)sz);

            for (i = 0; i < sz; i++)
                arr->Set(context, (int32_t)i, js_value(isolate, var[i]));

            return arr;
        }
        case fc::variant::type_id::object_type: {
            const fc::variant_object& o = var.get_object();
            v8::Local<v8::Object> obj = v8::Object::New(isolate->m_isolate);

            for (fc::variant_object::iterator it = o.begin(); it != o.end(); it++) {
                const std::string& key = it->key();
                obj->Set(context, isolate->NewString(key.c_str(), key.length()), js_value(isolate, it->value()));
            }

            return obj;
        }
        case fc::variant::type_id::blob_type: {
            const fc::blob& data = var.get_blob();
            obj_ptr<Buffer_base> buf = new Buffer(data.data.data(), (int32_t)data.data.size());
            return buf->wrap();
        }
        }

        return v;
    }

} // namespace chain
} // namespace eosio
