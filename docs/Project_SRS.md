# SkillProof Database ERD (Week 5)

​```mermaid
erDiagram
    USERS ||--o{ SKILL_ANALYSES : "runs"
    SKILL_ANALYSES ||--o{ SKILL_SCORES : "produces"
    SKILL_ANALYSES ||--o{ DIMENSION_SCORES : "produces"
    SKILL_ANALYSES ||--o{ LEARNING_GAPS : "produces"

    USERS {
        int user_id PK
        string full_name
        string email
        string password_hash
        string role
        string github_username
    }
    SKILL_ANALYSES {
        int analysis_id PK
        int user_id FK
        string github_username
        int total_repositories
        int total_stars
        timestamp analyzed_at
    }
    SKILL_SCORES {
        int score_id PK
        int analysis_id FK
        string skill_name
        int score
        string status
    }
    DIMENSION_SCORES {
        int dimension_id PK
        int analysis_id FK
        string dimension_name
        int score
    }
    LEARNING_GAPS {
        int gap_id PK
        int analysis_id FK
        string title
        string priority
    }
​```