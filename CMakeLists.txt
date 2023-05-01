cmake_minimum_required( VERSION 3.8 )

include(tools/arch.cmake)
include(tools/os.cmake)
include(tools/subdirs.cmake)

set(appname dmc)
set( CMAKE_CXX_STANDARD 17 )
set( CMAKE_CXX_EXTENSIONS ON )
set( CXX_STANDARD_REQUIRED ON)

project(${appname})

find_program(CCACHE_FOUND ccache)
if(CCACHE_FOUND)
	set_property(GLOBAL PROPERTY RULE_LAUNCH_COMPILE ccache)
	set_property(GLOBAL PROPERTY RULE_LAUNCH_LINK ccache)
endif(CCACHE_FOUND)

file(GLOB_RECURSE src_list "src/*.c*")
add_executable(${appname} ${src_list})

set(BIN_DIR ${PROJECT_SOURCE_DIR}/bin/${OS}_${ARCH}_${BUILD_TYPE})
set(FIBJS_DIR ${PROJECT_SOURCE_DIR}/fibjs/bin/${OS}_${ARCH}_${BUILD_TYPE})

set(EOS_DIR ${PROJECT_SOURCE_DIR}/eos)
if(${BUILD_TYPE} STREQUAL "release")
	set(EOS_BUILD_DIR ${EOS_DIR}/build_${OS}_Release)
else()
	set(EOS_BUILD_DIR ${EOS_DIR}/build_${OS}_Debug)
endif()

set(EXECUTABLE_OUTPUT_PATH ${BIN_DIR})

configure_file(${PROJECT_SOURCE_DIR}/tools/gitinfo.h.in ${CMAKE_CURRENT_BINARY_DIR}/gitinfo.h)

list(APPEND CMAKE_MODULE_PATH "${EOS_DIR}/libraries/fc/CMakeModules")

include(CheckIncludeFiles)
include(CheckCSourceCompiles)

set(CMAKE_C_FLAGS "${BUILD_OPTION}")

check_include_files(iconv.h HAVE_ICONV_H)
check_c_source_compiles("void posix_spawnp();
	__asm__(\".symver posix_spawnp,posix_spawnp@GLIBC_2.2.5\");
	void main(void){posix_spawnp();}" HAVE_GLIB_C_225_H)

check_c_source_compiles("void posix_spawnp();
	__asm__(\".symver posix_spawnp,posix_spawnp@GLIBC_2.2\");
	void main(void){posix_spawnp();}" HAVE_GLIB_C_22_H)

set(flags "-fsigned-char -fmessage-length=0 -fdata-sections -ffunction-sections -D_FILE_OFFSET_BITS=64")
set(ccflags "-std=c++17")
set(link_flags " ")

if(${OS} STREQUAL "Darwin")
 	set( CMAKE_C_FLAGS   "${CMAKE_C_FLAGS}   -Wall -Wno-deprecated-declarations" )
    set( CMAKE_CXX_FLAGS "${CMAKE_CXX_FLAGS} -Wall -Wno-deprecated-declarations" )

	set(flags "${flags} -Wno-nullability-completeness -mmacosx-version-min=10.15 -DOBJC_OLD_DISPATCH_PROTOTYPES=1")
	set(link_flags "${link_flags} -framework WebKit -framework Cocoa -framework Carbon -framework IOKit -mmacosx-version-min=10.15")

	target_link_libraries(${appname} dl iconv stdc++)

	find_library(security_framework security)
	find_library(localauthentication_framework localauthentication)
	find_library(corefoundation_framework corefoundation)
	find_library(cocoa_framework cocoa)
	target_link_libraries(${appname} ${security_framework} ${corefoundation_framework} ${localauthentication_framework} ${cocoa_framework})
endif()

if(${OS} STREQUAL "Linux")
	target_link_libraries(${appname} dl rt)
endif()

if(${OS} STREQUAL "FreeBSD")
	find_library(execinfo execinfo "/usr/local/lib" "/usr/lib")
	target_link_libraries(${appname} ${execinfo})
endif()

if(${BUILD_TYPE} STREQUAL "release")
	set(flags "${flags} -O3 -s ${BUILD_OPTION} -w -fvisibility=hidden")

	if(${OS} STREQUAL "FreeBSD")
		set(flags "${flags} -fno-omit-frame-pointer")
	else()
		set(flags "${flags} -fomit-frame-pointer")
	endif()

	set(link_flags "${link_flags} ${BUILD_OPTION}")
	add_definitions(-DNDEBUG=1)

	if(HAVE_GLIB_C_225_H)
		set(link_flags "${link_flags} -Wl,--wrap=memcpy")
	endif()

	if(${OS} STREQUAL "Linux")
		set(link_flags "${link_flags} -static-libstdc++ -static-libgcc -Wl,--no-as-needed")
	endif()
endif()

if(${BUILD_TYPE} STREQUAL "debug")
	set(flags "${flags} -g -O0 ${BUILD_OPTION} -Wall -Wno-overloaded-virtual")
	set(link_flags "${link_flags} ${BUILD_OPTION}")
	add_definitions(-DDEBUG=1)
endif()

set(CMAKE_C_FLAGS "${flags}")
set(CMAKE_CXX_FLAGS "${flags} ${ccflags}")

include_directories("${PROJECT_SOURCE_DIR}/include" 
	"${PROJECT_SOURCE_DIR}/fibjs/fibjs/include" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender/v8" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender/v8/include" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender/mbedtls" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender/uv" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender/uv/include" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender/zlib/include" 
	"${PROJECT_SOURCE_DIR}/fibjs/vender/msgpack/include"
	"${CMAKE_CURRENT_BINARY_DIR}")

MACRO(EOSLIBS dir)
	file(GLOB eos_libs "${dir}/*")
	foreach(eos_lib ${eos_libs})
		if(IS_DIRECTORY "${eos_lib}/include")
			include_directories("${eos_lib}/include")
		endif()
	endforeach()
ENDMACRO()

EOSLIBS("${EOS_DIR}/plugins")
EOSLIBS("${EOS_DIR}/libraries")

include_directories("${EOS_DIR}/libraries")
include_directories("${EOS_DIR}/externals/binaryen/src")
include_directories("${EOS_DIR}/libraries/wasm-jit/Include")
include_directories("${EOS_DIR}/libraries/builtins")
include_directories("${EOS_DIR}/libraries/softfloat/source/include")
include_directories("${EOS_DIR}/libraries/chain/include")
include_directories("${EOS_DIR}/libraries/chain/webassembly")
include_directories("${EOS_BUILD_DIR}/programs")
include_directories("${EOS_BUILD_DIR}/libraries/chain/include")
include_directories("${EOS_BUILD_DIR}/libraries/wabt")

if (APPLE)
	set(OPENSSL_INCLUDE_DIR "/usr/local/opt/openssl/include")
	set(OPENSSL_LIB_DIR "/usr/local/opt/openssl/lib")
elseif(UNIX AND NOT APPLE)
	set(OPENSSL_INCLUDE_DIR "/usr/include/openssl/include")
	set(OPENSSL_LIB_DIR "/usr/include/openssl")
else()
	message(FATAL_ERROR "openssl not found and don't know where to look, please specify OPENSSL_ROOT_DIR")
endif()

include_directories(${OPENSSL_INCLUDE_DIR})

MACRO(link_libs dir)
	file(GLOB_RECURSE _libs "${dir}/*.a")
	target_link_libraries(${appname} ${_libs})
ENDMACRO()

link_libs("${EOS_BUILD_DIR}/plugins")
link_libs("${EOS_BUILD_DIR}/libraries")
link_libs("${EOS_BUILD_DIR}/libraries/wasm-jit/Source")
link_libs("${EOS_BUILD_DIR}/externals/binaryen/lib")
link_libs("${EOS_BUILD_DIR}/libraries/fc")
link_libs("${EOS_BUILD_DIR}/libraries/wabt")
link_libs("${EOS_BUILD_DIR}/libraries/chain")
link_libs("${EOS_BUILD_DIR}/libraries/builtins")
link_libs("${EOS_BUILD_DIR}/plugins/blockvault_client_plugin")
link_libs("${EOS_BUILD_DIR}/libraries/wasm-jit/Source/WAST")

SET(BOOST_ROOT $ENV{BOOST_ROOT})
SET( Boost_USE_STATIC_LIBS ON CACHE STRING "ON or OFF" )
FIND_PACKAGE(Boost 1.72 REQUIRED COMPONENTS
    date_time
    filesystem
    system
    program_options
    chrono
    unit_test_framework
    iostreams)
	
include_directories(${Boost_INCLUDE_DIR})
target_link_libraries(${appname} ${Boost_LIBRARIES})

list(APPEND CMAKE_PREFIX_PATH "/usr/local/opt/llvm@4" "/usr/local/opt/gettext" "$ENV{HOME}/lib/cmake")

find_package(LLVM REQUIRED CONFIG)
llvm_map_components_to_libnames(LLVM_LIBS support core passes mcjit native DebugInfoDWARF orcjit)
target_link_libraries(${appname} ${LLVM_LIBS})

set(fibjs_libs fibjs jemalloc cef expat gumbo gd tiff jpeg png webp zlib leveldb snappy msgpack pcre sqlite mongo umysql uuid exif mbedtls v8 unzip uv exlib ev editline)

foreach(lib ${fibjs_libs})
	target_link_libraries(${appname} "${FIBJS_DIR}/lib${lib}.a")
endforeach()

if(${OS} STREQUAL "Darwin")
	target_link_libraries(${appname} "/usr/local/opt/gmp/lib/libgmp.a")
	target_link_libraries(${appname} pthread)
	target_link_libraries(${appname} 
		"${OPENSSL_LIB_DIR}/libcrypto.a"
		"${OPENSSL_LIB_DIR}/libssl.a")
	target_link_libraries(${appname} curl)
	target_link_libraries(${appname} "/usr/local/lib/libusb-1.0.a")
	target_link_libraries(${appname} edit)
else()
	target_link_libraries(${appname} gmp)
	target_link_libraries(${appname} pthread crypto ssl)
	target_link_libraries(${appname} usb-1.0 curl util)
endif()

set_target_properties(${appname} PROPERTIES LINK_FLAGS ${link_flags})