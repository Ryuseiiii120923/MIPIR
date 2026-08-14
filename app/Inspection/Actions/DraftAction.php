<?php

namespace App\Inspection\Actions;

class DraftAction{

    protected function key(int $ppf): string
    {
        return "landing_page.$ppf";
    }

    public function put(int $ppf, string $section, array $data): void
    {
        $draft = session($this->key($ppf),[]);
        $draft[$section] = $data;
        session([$this->key($ppf) => $draft]);
        logger('syncToParent called', ['draft' => $data]);
    }

    public function get(int $ppf): array
    {
        return session($this->key($ppf),[]);
    }

    public function clear(int $ppf): void
    {
        session()->forget($this->key($ppf));
    }
}