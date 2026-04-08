UNAME_S := $(shell uname -s)
ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
DC_EXEC_QA = docker run --init -it --rm --pull=always -v  "$$(pwd):/project" -w /project jakzal/phpqa:1.107.0-php8.4

.PHONY: build-env php-env run build-app

help:
	@awk 'BEGIN {FS = ":.*##"} \
		/^[a-zA-Z_-]+:.*##/ {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}' \
		$(MAKEFILE_LIST)

build-dev-env: ## Build dev environment
	@test -f .app.version || (echo 'v0.0.0' > .app.version && echo "✅ .app.version created")
	@test -f .env || (cp .env.dist .env && echo "✅ .env created from .env.dist")
	docker build -t sweeekcli-env -f docker/Dockerfile .

php-env: build-dev-env ## Access to dev environment
	docker run -v .:/app -i -t sweeekcli-env sh

run-app: ## Run app with php static
ifeq ($(UNAME_S),Darwin)
	bin/php-mac index.php $(ARGS)
else ifeq ($(UNAME_S),Linux)
	bin/php-linux index.php $(ARGS)
else
	echo "Not supported"
endif

build-app: build-dev-env ## Build cli application for multiple platforms
	rm -rf dist
	docker run -v .:/app -i -t sweeekcli-env box compile
	docker run -v .:/app -i -t sweeekcli-env phpacker build

composer: ## Run composer commands
ifeq ($(UNAME_S),Darwin)
	bin/php-mac bin/composer.phar $(ARGS)
else ifeq ($(UNAME_S),Linux)
	bin/php-linux bin/composer.phar $(ARGS)
else
	echo "Not supported"
endif

php-cs-fixer:
	$(DC_EXEC_QA) php-cs-fixer fix --config=.php_cs.dist.php --show-progress="dots" -v --using-cache=no

%:
	@:
