<?php
$content = file_get_contents('app/app/Modules/X-121/Actions/PersonLookupAction.php');
$search = <<<'CODE'
    public function search(int $businessId, string $query): array
    {
        return [];
    }
CODE;
$replace = <<<'CODE'
    public function search(int $businessId, string $query): array
    {
        return Person::where('business_id', $businessId)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })->get()->toArray();
    }
CODE;
$content = str_replace($search, $replace, $content);
file_put_contents('app/app/Modules/X-121/Actions/PersonLookupAction.php', $content);
