<?php

namespace App\Federation\Signatures;

use InvalidArgumentException;

class StructuredFields
{
    protected string $input;

    protected int $pos = 0;

    protected int $length = 0;

    public static function parseDictionary(string $input): array
    {
        return (new self)->dictionary($input);
    }

    public static function serializeInnerList(array $items, array $params): string
    {
        $parts = [];

        foreach ($items as $item) {
            $parts[] = self::serializeBareItem($item['value']).self::serializeParams($item['params'] ?? []);
        }

        return '('.implode(' ', $parts).')'.self::serializeParams($params);
    }

    public static function serializeParams(array $params): string
    {
        $out = '';

        foreach ($params as $key => $value) {
            $out .= ';'.$key;

            if ($value !== true) {
                $out .= '='.self::serializeBareItem($value);
            }
        }

        return $out;
    }

    public static function serializeBareItem(mixed $value): string
    {
        if ($value instanceof ByteSequence) {
            return ':'.base64_encode($value->bytes).':';
        }

        if ($value instanceof Token) {
            return $value->value;
        }

        if (is_bool($value)) {
            return $value ? '?1' : '?0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        }

        if (is_string($value)) {
            if (preg_match('/[\x00-\x1f\x7f-\xff]/', $value)) {
                throw new InvalidArgumentException('Invalid character in structured field string');
            }

            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        throw new InvalidArgumentException('Unsupported structured field value');
    }

    protected function dictionary(string $input): array
    {
        $this->input = $input;
        $this->length = strlen($input);
        $this->pos = 0;
        $this->skipSpaces();

        $members = [];

        while ($this->pos < $this->length) {
            $key = $this->key();

            if ($this->peek() === '=') {
                $this->pos++;
                $members[$key] = $this->itemOrInnerList();
            } else {
                $members[$key] = ['value' => true, 'params' => $this->params()];
            }

            $this->skipOws();

            if ($this->pos >= $this->length) {
                return $members;
            }

            if ($this->peek() !== ',') {
                throw new InvalidArgumentException('Expected comma in dictionary');
            }

            $this->pos++;
            $this->skipOws();

            if ($this->pos >= $this->length) {
                throw new InvalidArgumentException('Trailing comma in dictionary');
            }
        }

        return $members;
    }

    protected function itemOrInnerList(): array
    {
        if ($this->peek() === '(') {
            return $this->innerList();
        }

        return ['value' => $this->bareItem(), 'params' => $this->params()];
    }

    protected function innerList(): array
    {
        $this->pos++;
        $items = [];

        while ($this->pos < $this->length) {
            $this->skipSpaces();

            if ($this->peek() === ')') {
                $this->pos++;

                return ['value' => $items, 'params' => $this->params(), 'inner' => true];
            }

            $items[] = ['value' => $this->bareItem(), 'params' => $this->params()];

            $next = $this->peek();

            if ($next !== ' ' && $next !== ')') {
                throw new InvalidArgumentException('Malformed inner list');
            }
        }

        throw new InvalidArgumentException('Unterminated inner list');
    }

    protected function params(): array
    {
        $params = [];

        while ($this->peek() === ';') {
            $this->pos++;
            $this->skipSpaces();
            $key = $this->key();
            $value = true;

            if ($this->peek() === '=') {
                $this->pos++;
                $value = $this->bareItem();
            }

            $params[$key] = $value;
        }

        return $params;
    }

    protected function key(): string
    {
        if (! preg_match('/\G[a-z*][a-z0-9_\-.*]*/', $this->input, $m, 0, $this->pos)) {
            throw new InvalidArgumentException('Invalid structured field key');
        }

        $this->pos += strlen($m[0]);

        return $m[0];
    }

    protected function bareItem(): mixed
    {
        $c = $this->peek();

        if ($c === '"') {
            return $this->string();
        }

        if ($c === ':') {
            return $this->byteSequence();
        }

        if ($c === '?') {
            return $this->boolean();
        }

        if ($c === '-' || ($c !== null && ctype_digit($c))) {
            return $this->number();
        }

        if ($c !== null && preg_match('/[A-Za-z*]/', $c)) {
            return $this->token();
        }

        throw new InvalidArgumentException('Invalid structured field item');
    }

    protected function string(): string
    {
        $this->pos++;
        $out = '';

        while ($this->pos < $this->length) {
            $c = $this->input[$this->pos++];

            if ($c === '\\') {
                $n = $this->input[$this->pos++] ?? null;

                if ($n !== '"' && $n !== '\\') {
                    throw new InvalidArgumentException('Invalid escape in string');
                }

                $out .= $n;

                continue;
            }

            if ($c === '"') {
                return $out;
            }

            $o = ord($c);

            if ($o < 0x20 || $o > 0x7E) {
                throw new InvalidArgumentException('Invalid character in string');
            }

            $out .= $c;
        }

        throw new InvalidArgumentException('Unterminated string');
    }

    protected function byteSequence(): ByteSequence
    {
        $this->pos++;
        $end = strpos($this->input, ':', $this->pos);

        if ($end === false) {
            throw new InvalidArgumentException('Unterminated byte sequence');
        }

        $encoded = substr($this->input, $this->pos, $end - $this->pos);
        $this->pos = $end + 1;

        if (! preg_match('#^[A-Za-z0-9+/]*={0,2}$#', $encoded)) {
            throw new InvalidArgumentException('Invalid byte sequence');
        }

        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid byte sequence');
        }

        return new ByteSequence($decoded);
    }

    protected function boolean(): bool
    {
        $this->pos++;
        $c = $this->input[$this->pos++] ?? null;

        if ($c === '1') {
            return true;
        }

        if ($c === '0') {
            return false;
        }

        throw new InvalidArgumentException('Invalid boolean');
    }

    protected function number(): int|float
    {
        if (! preg_match('/\G-?\d{1,15}(\.\d{1,3})?/', $this->input, $m, 0, $this->pos)) {
            throw new InvalidArgumentException('Invalid number');
        }

        $this->pos += strlen($m[0]);

        return isset($m[1]) ? (float) $m[0] : (int) $m[0];
    }

    protected function token(): Token
    {
        preg_match('/\G[A-Za-z*][A-Za-z0-9!#$%&\'*+\-.^_`|~:\/]*/', $this->input, $m, 0, $this->pos);
        $this->pos += strlen($m[0]);

        return new Token($m[0]);
    }

    protected function peek(): ?string
    {
        return $this->input[$this->pos] ?? null;
    }

    protected function skipSpaces(): void
    {
        while (($this->input[$this->pos] ?? null) === ' ') {
            $this->pos++;
        }
    }

    protected function skipOws(): void
    {
        while (in_array($this->input[$this->pos] ?? null, [' ', "\t"], true)) {
            $this->pos++;
        }
    }
}
