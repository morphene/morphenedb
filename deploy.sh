#!/bin/bash
rsync ./ morphenedb:/var/www/morphenedb/ --rsh ssh --rsync-path="sudo rsync" --recursive --perms --delete --verbose --exclude=.git* --exclude=docker/**/*.service --exclude=cache --exclude=vendor/ezyang/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer/ --exclude=vendor --exclude=app/config --exclude=app/storage/views/*.php --checksum -a
