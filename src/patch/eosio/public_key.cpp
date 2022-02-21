#include <fc/crypto/elliptic.hpp>
#include <fc/crypto/elliptic_r1.hpp>
#include <fc/crypto/signature.hpp>
#include <fc/reflect/reflect.hpp>
#include <fc/reflect/variant.hpp>
#include <fc/static_variant.hpp>

#define private public
#define public_key_legacy_prefix public_key_legacy_prefix_eos
#include <fc/crypto/public_key.hpp>
#undef public_key_legacy_prefix
#undef private

namespace fc {
namespace crypto {
    namespace config {
        extern const char* public_key_legacy_prefix;
    };
};
};
#define constexpr

#include <fc/src/crypto/public_key.cpp>