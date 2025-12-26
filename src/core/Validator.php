<?php
namespace Core;

class Validator {
    public static function make(array $input, array $rules): array {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $value = $input[$field] ?? null;
            $rulesArr = explode('|', $ruleStr);
            foreach ($rulesArr as $rule) {
                $parts = explode(':', $rule, 2);
                $name = $parts[0];
                $param = $parts[1] ?? null;
                $err = self::validateRule($name, $value, $param, $field);
                if ($err) { $errors[$field][] = $err; }
            }
        }
        return $errors;
    }

    private static function validateRule(string $name, $value, $param, string $field): ?string {
        if ($name === 'required') {
            if ($value === null || $value === '') return "$field wajib diisi";
        }
        if ($name === 'nullable') return null;
        if ($value === null || $value === '') return null; // skip other checks

        switch ($name) {
            case 'max':
                $max = (int)$param;
                if (is_string($value) && mb_strlen($value) > $max) return "$field maksimal $max karakter";
                break;
            case 'numeric':
                if (!is_numeric($value)) return "$field harus numerik";
                break;
            case 'integer':
                if (!is_int($value) && !ctype_digit((string)$value)) return "$field harus integer";
                break;
            case 'min':
                $min = (float)$param;
                if (is_numeric($value) && (float)$value < $min) return "$field minimal $min";
                break;
            case 'date':
                if (!self::isDate((string)$value)) return "$field harus tanggal (YYYY-MM-DD)";
                break;
            case 'in':
                $opts = array_map('trim', explode(',', (string)$param));
                if (!in_array((string)$value, $opts, true)) return "$field harus salah satu dari: ".implode(',', $opts);
                break;
        }
        return null;
    }

    private static function isDate(string $v): bool {
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v;
    }
}
