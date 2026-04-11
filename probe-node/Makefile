.PHONY: build test clean docker-build docker-push install deps lint

# Build variables
BINARY_NAME=probe-node
VERSION?=dev
BUILD_TIME=$(shell date -u '+%Y-%m-%d_%H:%M:%S')
LDFLAGS=-ldflags "-X main.version=${VERSION} -X main.buildTime=${BUILD_TIME} -s -w"

# Go parameters
GOCMD=go
GOBUILD=$(GOCMD) build
GOCLEAN=$(GOCMD) clean
GOTEST=$(GOCMD) test
GOGET=$(GOCMD) get
GOMOD=$(GOCMD) mod

# Docker parameters
DOCKER_IMAGE=easymonitor/probe-node
DOCKER_TAG?=latest

all: test build

## build: Build the binary
build:
	@echo "Building ${BINARY_NAME}..."
	$(GOBUILD) $(LDFLAGS) -o bin/$(BINARY_NAME) ./cmd/probe

## build-linux: Build for Linux amd64
build-linux:
	@echo "Building for Linux amd64..."
	GOOS=linux GOARCH=amd64 $(GOBUILD) $(LDFLAGS) -o bin/$(BINARY_NAME)-linux-amd64 ./cmd/probe

## build-linux-arm: Build for Linux arm64
build-linux-arm:
	@echo "Building for Linux arm64..."
	GOOS=linux GOARCH=arm64 $(GOBUILD) $(LDFLAGS) -o bin/$(BINARY_NAME)-linux-arm64 ./cmd/probe

## test: Run tests
test:
	@echo "Running tests..."
	$(GOTEST) -v -race -coverprofile=coverage.out ./...

## test-coverage: Run tests with coverage report
test-coverage: test
	@echo "Generating coverage report..."
	$(GOCMD) tool cover -html=coverage.out -o coverage.html

## clean: Clean build files
clean:
	@echo "Cleaning..."
	$(GOCLEAN)
	rm -rf bin/
	rm -f coverage.out coverage.html

## deps: Download dependencies
deps:
	@echo "Downloading dependencies..."
	$(GOMOD) download
	$(GOMOD) tidy

## lint: Run linters
lint:
	@echo "Running linters..."
	@if command -v golangci-lint > /dev/null; then \
		golangci-lint run ./...; \
	else \
		echo "golangci-lint not installed, running go vet..."; \
		go vet ./...; \
	fi

## docker-build: Build Docker image
docker-build:
	@echo "Building Docker image..."
	docker build -t $(DOCKER_IMAGE):$(DOCKER_TAG) .

## docker-build-multiarch: Build multi-architecture Docker image
docker-build-multiarch:
	@echo "Building multi-arch Docker image..."
	docker buildx build --platform linux/amd64,linux/arm64 -t $(DOCKER_IMAGE):$(DOCKER_TAG) .

## docker-push: Push Docker image
docker-push:
	@echo "Pushing Docker image..."
	docker push $(DOCKER_IMAGE):$(DOCKER_TAG)

## run: Run the application
run: build
	@echo "Running ${BINARY_NAME}..."
	./bin/$(BINARY_NAME)

## help: Show this help message
help:
	@echo "Usage: make [target]"
	@echo ""
	@echo "Targets:"
	@sed -n 's/^##//p' ${MAKEFILE_LIST} | column -t -s ':' | sed -e 's/^/ /'
