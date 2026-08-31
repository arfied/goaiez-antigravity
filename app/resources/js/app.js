// ⛔ THE TOASTER'S JAVASCRIPT WAS NEVER BUNDLED, AND WITHOUT IT THE HUB IS A
// LOADED GUN — decision 5470. `<x-toaster-hub />` renders
// `x-data="toasterHub(...)"`, so a page carrying the hub without this import
// throws `ReferenceError: toasterHub is not defined` during Alpine's init —
// which does not merely lose the toast. It aborts the walk of the tree, so
// every `wire:click` after it never binds and the whole screen goes dead.
//
// That is the shape of the bug the owner reported twice: a Publish button that
// did nothing. Before the hub existed the click worked and the refusal was
// delivered nowhere; after the hub was added without this line the click
// stopped working at all. One missing import, two different silences.
//
// ⛔ THE MECHANISM ABOVE IS OVERSTATED AND THE CONCLUSION IS NOT — MEASURED
// 2026-08-26 BY DELETING THIS LINE, REBUILDING, AND DRIVING A REAL BROWSER
// (decision 10106). "It aborts the walk of the tree, so every wire:click after
// it never binds" is not what Alpine 3.15.12 does: `handleError` logs and
// rethrows on a `setTimeout`, so the walk CONTINUES — the `<template
// x-for="toasts">` inside the failing hub was reached and errored in its turn,
// and every `wire:click` on the screen went on binding and round-tripping. Both
// readings are kept because the old one is why this line exists.
//
// ⚠️ AND NEITHER READING WAS EVER EXERCISED HERE, WHICH IS THE PART TO CARRY:
// both layouts render `<x-toaster-hub />` as the LAST element in the body, so
// there is nothing "after it" to lose. What is genuinely lost without this
// import is every toast in the application — decision 5460's cost exactly, and
// unchanged. ⚠️ `TemplateLintTest`'s failure message carries the same
// overstatement and is deliberately not edited from here; it is another lane's
// file this wave.
//
// ⚠️ THAT LINT AND `tests/Browser/AccountScreenTest.php` ARE A PAIR AND NEITHER
// COVERS THE OTHER. The lint proves this line is WRITTEN and cannot prove the
// bundle defines `toasterHub`; the browser test proves the bundle defines it and
// would pass on a stale `public/build` whose source had lost the line. Both, or
// the property is only half held.
//
// The path is the package's own documented one, read out of its README in
// `vendor/` rather than recalled.
import '../../vendor/masmerise/livewire-toaster/resources/js';

import './audit';
