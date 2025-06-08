param(
    [Parameter(Mandatory=$true)]
    [ValidateSet("build", "up", "down", "logs", "clean", "dev", "dev-down")]
    [string]$Action
)

switch ($Action) {
    "build" {
        docker compose build
    }
    "up" {
        docker compose up -d
    }
    "down" {
        docker compose down
    }
    "logs" {
        docker compose logs -f
    }
    "clean" {
        docker compose down --volumes --remove-orphans
        docker system prune -f
    }
    "dev" {
        docker compose -f docker-compose-dev.yml up -d --build
    }
    "dev-down" {
        docker compose -f docker-compose-dev.yml down
    }
}
