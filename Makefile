default:
	gcc -o gotit_x64.so system.c --share -fPIC -ldl
	gcc -o gotit_x86.so system.c --share -fPIC -ldl -m32
	strip -s gotit_x64.so
	strip -s gotit_x86.so
