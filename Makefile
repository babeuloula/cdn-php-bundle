-include docker/.env

.SILENT: cache shell analyse
.DEFAULT_GOAL := help

help:
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

##
## Project
##---------------------------------------------------------------------------

install: ## Install the project
install: hooks
	cd ./docker && ./install.sh

destroy: ## Destroy the docker-compose stack
destroy:
	cd ./docker && docker compose rm -f

hooks:
	# Pre commit
	echo "#!/bin/bash" > .git/hooks/pre-commit
	echo "DISABLE_TTY=1 make check" >> .git/hooks/pre-commit
	chmod +x .git/hooks/pre-commit
	# Git pull
	echo "#!/bin/bash" > .git/hooks/post-merge
	echo "DISABLE_TTY=1 make post-merge" >> .git/hooks/post-merge
	chmod +x .git/hooks/post-merge

post-merge: composer

composer:
	docker/exec composer install

shell: ## Connect to PHP container
shell:
	docker/exec

cache:
	mkdir -p .cache

##
## Code quality
##---------------------------------------------------------------------------

check: lint analyse security

lint: ## Execute PHPCS
lint: cache
	docker/exec vendor/bin/phpcs -p --report-full --report-checkstyle=./.cache/phpcs-report.xml

fixer: ## Execute PHPCS fixer
fixer: cache
	docker/exec vendor/bin/phpcbf -p

analyse: ## Execute PHPStan
analyse: cache
	docker/exec vendor/bin/phpstan analyse --memory-limit=4G

security: ## Check CVE for vendor dependencies
security:
	docker/exec composer audit
