<?php
$content = file_get_contents('app/app/Modules/X-01/Ui/Thread.php');
$search = <<<'CODE'
        return Person::where('business_id', $this->customer->business_id)
            ->where(function ($q) {
                if ($this->customer->email) {
                    $q->where('email', $this->customer->email);
                }
                if ($this->customer->phone) {
                    $q->orWhere('phone', $this->customer->phone);
                }
            })
            ->value('id');
CODE;
$replace = <<<'CODE'
        $list = app(PersonLookupAction::class)->listForBusiness(
            $this->customer->business_id,
            $this->customer->email,
            $this->customer->phone
        );

        return !empty($list) ? $list[0]['id'] : null;
CODE;
$content = str_replace($search, $replace, $content);
file_put_contents('app/app/Modules/X-01/Ui/Thread.php', $content);
