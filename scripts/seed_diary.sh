#!/bin/bash

docker exec --user www-data master-nextcloud-1 php apps-extra/structureddiary/scripts/seed_mood_observation_diary.php admin
