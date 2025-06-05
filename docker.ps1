param(
    [Parameter(Mandatory=$true)]
    [ValidateSet("build", "up", "down", "logs", "clean", "prod", "prod-down")]
    [string]$Action
)

switch ($Action) {
    "build" {
        docker compose build
    }
    "up" {
        docker compose up -d --build
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
    "prod" {
        docker compose -f docker-compose.prod.yml up -d --build
    }
    "prod-down" {
        docker compose -f docker-compose.prod.yml down
    }
}
