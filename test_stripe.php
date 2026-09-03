<?php
require 'app/vendor/autoload.php';

echo class_exists('\Stripe\StripeClient') ? "yes" : "no";
