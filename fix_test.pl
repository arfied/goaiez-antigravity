my $in_head = 0;
my $in_theirs = 0;
open my $in, '<', 'app/tests/Feature/Architecture/HeadingSeamTest.php' or die $!;
open my $out, '>', 'app/tests/Feature/Architecture/HeadingSeamTest.php.new' or die $!;
while (<$in>) {
    if (/^<<<<<<< HEAD/) {
        $in_head = 1;
        next;
    }
    if (/^=======/) {
        $in_head = 0;
        $in_theirs = 1;
        next;
    }
    if (/^>>>>>>> 908af867e/) {
        $in_theirs = 0;
        next;
    }
    if ($in_head) {
        print $out $_;
    } elsif (!$in_theirs) {
        print $out $_;
    }
}
close $in;
close $out;
rename 'app/tests/Feature/Architecture/HeadingSeamTest.php.new', 'app/tests/Feature/Architecture/HeadingSeamTest.php';
