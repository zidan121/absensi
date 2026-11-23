<?php

use CodeIgniter\CLI\CLI;

// Display the exception and its original location in the terminal.
CLI::write('[' . $exception::class . ']', 'light_gray', 'red');
CLI::write($message);
CLI::write('at ' . CLI::color(clean_path($exception->getFile()) . ':' . $exception->getLine(), 'green'));
CLI::newLine();

$last = $exception;
while ($previous = $last->getPrevious()) {
    $last = $previous;
    CLI::write('Caused by: [' . $previous::class . ']', 'red');
    CLI::write($previous->getMessage());
    CLI::write('at ' . CLI::color(clean_path($previous->getFile()) . ':' . $previous->getLine(), 'green'));
    CLI::newLine();
}

if (defined('SHOW_DEBUG_BACKTRACE') && SHOW_DEBUG_BACKTRACE) {
    foreach ($last->getTrace() as $index => $trace) {
        $location = isset($trace['file'])
            ? clean_path($trace['file']) . ':' . ($trace['line'] ?? '?')
            : '[internal function]';
        CLI::write(str_pad((string) ($index + 1), 3, ' ', STR_PAD_LEFT) . ' ' . $location);
        if (isset($trace['class'], $trace['function'])) {
            CLI::write('    ' . $trace['class'] . ($trace['type'] ?? '::') . $trace['function'] . '()');
        } elseif (isset($trace['function'])) {
            CLI::write('    ' . $trace['function'] . '()');
        }
    }
}
