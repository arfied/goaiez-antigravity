#!/bin/bash
sudo -u postgres psql -d goaiez_antig_dev -c "CREATE EXTENSION IF NOT EXISTS vector;"
sudo -u postgres psql -d goaiez_antig_test -c "CREATE EXTENSION IF NOT EXISTS vector;"
cd /home/goaiez/agents/grs-antig/app && bash install-step-0.sh
