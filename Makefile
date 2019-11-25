serve:
	bin/console server:run

cs:
	./vendor/bin/php-cs-fixer fix --verbose

cs_dry_run:
	./vendor/bin/php-cs-fixer fix --verbose --dry-run

test:
	SYMFONY_DEPRECATIONS_HELPER="max[self]=0" bin/phpunit
