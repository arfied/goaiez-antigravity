<?php
function release() { throw new \Exception("Release failed"); }
function parentTearDown() { echo "parent::tearDown ran\n"; }
function tearDown() {
    try {
        release();
    } catch (\Exception $e) {
        if ($e->getMessage() !== '25P02') {
            throw $e;
        }
    } finally {
        parentTearDown();
    }
}
try {
    tearDown();
} catch (\Throwable $e) {
    echo "Caught in caller: " . $e->getMessage() . "\n";
}
