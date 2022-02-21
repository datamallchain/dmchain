/*
 * dmc.cpp
 *
 *  Created on: Jun 1, 2018
 *      Author: lion
 */

#include "fibjs.h"
#include "object.h"
#include "include/libplatform/libplatform.h"
#include <eosio/chain/transaction_context.hpp>

namespace fibjs {
void importModule()
{
    IMPORT_MODULE(assert);
    IMPORT_MODULE(base32);
    IMPORT_MODULE(base64);
    IMPORT_MODULE(buffer);
    IMPORT_MODULE(child_process);
    IMPORT_MODULE(coroutine);
    IMPORT_MODULE(console);
    IMPORT_MODULE(constants);
    IMPORT_MODULE(crypto);
    IMPORT_MODULE(db);
    IMPORT_MODULE(dgram);
    IMPORT_MODULE(dns);
    IMPORT_MODULE(encoding);
    IMPORT_MODULE(events);
    IMPORT_MODULE(fs);
    IMPORT_MODULE(gd);
    IMPORT_MODULE(gui);
    IMPORT_MODULE(hash);
    IMPORT_MODULE(hex);
    IMPORT_MODULE(http);
    IMPORT_MODULE(https);
    IMPORT_MODULE(iconv);
    IMPORT_MODULE(io);
    IMPORT_MODULE(json);
    IMPORT_MODULE(msgpack);
    IMPORT_MODULE(mq);
    IMPORT_MODULE(net);
    IMPORT_MODULE(os);
    IMPORT_MODULE(path);
    IMPORT_MODULE(perf_hooks);
    IMPORT_MODULE(process);
    IMPORT_MODULE(profiler);
    IMPORT_MODULE(punycode);
    IMPORT_MODULE(querystring);
    IMPORT_MODULE(ssl);
    IMPORT_MODULE(tls);
    IMPORT_MODULE(string_decoder);
    IMPORT_MODULE(test);
    IMPORT_MODULE(timers);
    IMPORT_MODULE(tty);
    IMPORT_MODULE(url);
    IMPORT_MODULE(util);
    IMPORT_MODULE(uuid);
    IMPORT_MODULE(vm);
    IMPORT_MODULE(worker_threads);
    IMPORT_MODULE(ws);
    IMPORT_MODULE(xml);
    IMPORT_MODULE(zip);
    IMPORT_MODULE(zlib);

    IMPORT_MODULE(dmc)
}

int init_eos();
} // namespace fibjs

int32_t main(int32_t argc, char* argv[])
{
    class Platform : public v8::Platform {
    public:
        static std::unique_ptr<v8::Platform> platform_creator()
        {
            return std::unique_ptr<v8::Platform>(new Platform());
        }

        explicit Platform()
            : platform_(v8::platform::NewDefaultPlatform().release())
        {
        }

        v8::PageAllocator* GetPageAllocator() override
        {
            return platform_->GetPageAllocator();
        }

        void OnCriticalMemoryPressure() override
        {
            platform_->OnCriticalMemoryPressure();
        }

        bool OnCriticalMemoryPressure(size_t length) override
        {
            return platform_->OnCriticalMemoryPressure(length);
        }

        std::shared_ptr<v8::TaskRunner> GetForegroundTaskRunner(v8::Isolate* isolate) override
        {
            return platform_->GetForegroundTaskRunner(isolate);
        }

        int NumberOfWorkerThreads() override
        {
            return platform_->NumberOfWorkerThreads();
        }

        void CallOnWorkerThread(std::unique_ptr<v8::Task> task) override
        {
            platform_->CallOnWorkerThread(std::move(task));
        }

        void CallDelayedOnWorkerThread(std::unique_ptr<v8::Task> task, double delay_in_seconds) override
        {
            platform_->CallDelayedOnWorkerThread(std::move(task), delay_in_seconds);
        }

        void CallOnForegroundThread(v8::Isolate* isolate, v8::Task* task) override
        {
            platform_->CallOnForegroundThread(isolate, task);
        }

        void CallDelayedOnForegroundThread(v8::Isolate* isolate, v8::Task* task, double delay_in_seconds) override
        {
            platform_->CallDelayedOnForegroundThread(isolate, task, delay_in_seconds);
        }

        void CallIdleOnForegroundThread(v8::Isolate* isolate, v8::IdleTask* task) override
        {
            platform_->CallIdleOnForegroundThread(isolate, task);
        }

        bool IdleTasksEnabled(v8::Isolate* isolate) override
        {
            return platform_->IdleTasksEnabled(isolate);
        }

        double MonotonicallyIncreasingTime() override
        {
            return platform_->MonotonicallyIncreasingTime();
        }

        double CurrentClockTimeMillis() override
        {
            return platform_->CurrentClockTimeMillis();
        }

        v8::TracingController* GetTracingController() override
        {
            return platform_->GetTracingController();
        }

    private:
        v8::Platform* platform_;
    };

    fibjs::init_eos();
    fibjs::importModule();

    fibjs::start(argc, argv, fibjs::FiberProcJsEntry, Platform::platform_creator);
    fibjs::run_gui(argc, argv);

    return 0;
}
