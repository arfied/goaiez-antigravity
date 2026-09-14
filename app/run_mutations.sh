#!/bin/bash
set -e

# Wait for W1 to finish (pest)
while pgrep -f pest >/dev/null; do sleep 1; done

echo "Running W2..."
sed -i "s/->value('person_id')/->firstOrFail()->person_id/g" app/Modules/X-171/Actions/JobStateAction.php
./vendor/bin/pest > ../.agents/supervisor/pb153-w2.red.log 2>&1 || true
git checkout app/Modules/X-171/Actions/JobStateAction.php

echo "Running W3..."
sed -i "s/->where('business_id', \$businessId)/->where('business_id', 0)/g" app/Modules/X-171/Actions/JobStateAction.php
./vendor/bin/pest > ../.agents/supervisor/pb153-w3.red.log 2>&1 || true
git checkout app/Modules/X-171/Actions/JobStateAction.php

echo "Re-applying fix..."
sed -i "s/->where('id', \$jobId)/->where('business_id', \$businessId)\n                ->where('id', \$jobId)/g" app/Modules/X-171/Actions/JobStateAction.php
./vendor/bin/pint app/Modules/X-171/Actions/JobStateAction.php
