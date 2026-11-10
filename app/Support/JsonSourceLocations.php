<?php

namespace App\Support;

/** Locates records in already validated JSON, without retaining the source. */
final class JsonSourceLocations
{
    private int $offset = 0;
    private int $line = 1;

    private function __construct(private readonly string $source) {}

    public static function rows(string $source, ?string $wrapper = null): array
    {
        $reader = new self($source);
        $reader->whitespace();
        if (($source[$reader->offset] ?? '') === '[') return $reader->records('$');
        $rootLine = $reader->line;
        if (($source[$reader->offset] ?? '') !== '{') return [];
        $reader->offset++;
        $found = [];
        while (true) {
            $reader->whitespace();
            if (($source[$reader->offset] ?? '') === '}') break;
            $start = $reader->offset;
            $reader->value();
            $key = json_decode(substr($source, $start, $reader->offset - $start), true);
            $reader->whitespace();
            $reader->offset++; // colon; JSON syntax was validated before this reader.
            $reader->whitespace();
            if (in_array($key, ['data', 'aaData'], true) && ($wrapper === null || $wrapper === $key)
                && ($wrapper !== null || in_array($source[$reader->offset] ?? '', ['[', '{'], true))) {
                $found[$key] = $reader->records('$.'.$key);
            } else {
                $reader->value();
            }
            $reader->whitespace();
            if (($source[$reader->offset] ?? '') !== ',') break;
            $reader->offset++;
        }
        if ($wrapper !== null) return $found[$wrapper] ?? [['path' => '$', 'line' => $rootLine]];
        return $found['data'] ?? $found['aaData'] ?? [['path' => '$', 'line' => $rootLine]];
    }

    private function records(string $path): array
    {
        $this->whitespace();
        if (($this->source[$this->offset] ?? '') !== '[') {
            $location = ['path' => $path, 'line' => $this->line];
            $this->value();
            return [$location];
        }
        $this->offset++;
        $records = [];
        $this->whitespace();
        while (($this->source[$this->offset] ?? ']') !== ']') {
            $records[] = ['path' => $path.'['.count($records).']', 'line' => $this->line];
            $this->value();
            $this->whitespace();
            if (($this->source[$this->offset] ?? '') !== ',') break;
            $this->offset++;
            $this->whitespace();
        }
        $this->offset++; // closing bracket
        return $records;
    }

    private function whitespace(): void
    {
        while (isset($this->source[$this->offset]) && str_contains(" \t\r\n", $this->source[$this->offset])) {
            if ($this->source[$this->offset] === "\n") $this->line++;
            $this->offset++;
        }
    }

    private function value(): void
    {
        $this->whitespace();
        $depth = 0;
        $inString = false;
        while (isset($this->source[$this->offset])) {
            $char = $this->source[$this->offset];
            if ($inString) {
                $this->offset++;
                if ($char === '\\') { $this->offset++; continue; }
                if ($char === '"') {
                    $inString = false;
                    if ($depth === 0) return;
                }
                continue;
            }
            if ($char === '"') { $inString = true; $this->offset++; continue; }
            if ($char === '{' || $char === '[') $depth++;
            elseif ($char === '}' || $char === ']') {
                if ($depth === 0) return;
                $depth--;
                $this->offset++;
                if ($depth === 0) return;
                continue;
            } elseif ($depth === 0 && ($char === ',' || str_contains(" \t\r\n:", $char))) return;
            if ($char === "\n") $this->line++;
            $this->offset++;
        }
    }
}
