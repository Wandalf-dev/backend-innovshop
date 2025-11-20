# API BudgetGoal - Documentation

## Endpoint

Base URL: `/api/budget-goals`

## Opérations disponibles

- `GET /api/budget-goals` - Liste des objectifs de l'utilisateur connecté
- `GET /api/budget-goals/{id}` - Détails d'un objectif spécifique
- `POST /api/budget-goals` - Créer un nouvel objectif
- `PATCH /api/budget-goals/{id}` - Modifier un objectif existant
- `PUT /api/budget-goals/{id}` - Remplacer un objectif existant
- `DELETE /api/budget-goals/{id}` - Supprimer un objectif

## Sécurité

- Toutes les opérations nécessitent une authentification (`ROLE_USER`)
- Les utilisateurs ne peuvent voir/modifier que leurs propres objectifs
- Les admins peuvent voir tous les objectifs

## Filtres disponibles

- `goalType` : Filtrer par type d'objectif (exact match)
  - Exemple: `/api/budget-goals?goalType=monthly`
  
- `endDate[after]` : Filtrer les objectifs avec une date de fin future
  - Exemple: `/api/budget-goals?endDate[after]=2024-12-31`

- `order[createdAt]` : Trier par date de création (par défaut: DESC)
  - Exemple: `/api/budget-goals?order[createdAt]=ASC`

## Exemples de payloads

### GET /api/budget-goals - Liste des objectifs

**Réponse (200 OK):**

```json
{
  "hydra:member": [
    {
      "@id": "/api/budget-goals/1",
      "@type": "BudgetGoal",
      "id": 1,
      "label": "Budget mensuel janvier",
      "goalType": "monthly",
      "targetAmount": "500.00",
      "currentAmount": "250.50",
      "startDate": "2024-01-01",
      "endDate": "2024-01-31",
      "createdAt": "2024-01-01T10:00:00+00:00",
      "updatedAt": "2024-01-15T14:30:00+00:00",
      "progressPercentage": 50.1
    },
    {
      "@id": "/api/budget-goals/2",
      "@type": "BudgetGoal",
      "id": 2,
      "label": "Économies vacances",
      "goalType": "savings",
      "targetAmount": "2000.00",
      "currentAmount": "1500.00",
      "startDate": "2024-01-01",
      "endDate": "2024-06-30",
      "createdAt": "2024-01-01T10:00:00+00:00",
      "updatedAt": null,
      "progressPercentage": 75.0
    }
  ],
  "hydra:totalItems": 2
}
```

### GET /api/budget-goals/{id} - Détails d'un objectif

**Réponse (200 OK):**

```json
{
  "@id": "/api/budget-goals/1",
  "@type": "BudgetGoal",
  "id": 1,
  "label": "Budget mensuel janvier",
  "goalType": "monthly",
  "targetAmount": "500.00",
  "currentAmount": "250.50",
  "startDate": "2024-01-01",
  "endDate": "2024-01-31",
  "createdAt": "2024-01-01T10:00:00+00:00",
  "updatedAt": "2024-01-15T14:30:00+00:00",
  "progressPercentage": 50.1
}
```

### POST /api/budget-goals - Créer un objectif

**Requête:**

```json
{
  "label": "Budget mensuel février",
  "goalType": "monthly",
  "targetAmount": "600.00",
  "currentAmount": "0.00",
  "startDate": "2024-02-01",
  "endDate": "2024-02-29"
}
```

**Note:** Le champ `user` n'est pas nécessaire dans la requête. Il sera automatiquement assigné à l'utilisateur connecté.

**Réponse (201 Created):**

```json
{
  "@id": "/api/budget-goals/3",
  "@type": "BudgetGoal",
  "id": 3,
  "label": "Budget mensuel février",
  "goalType": "monthly",
  "targetAmount": "600.00",
  "currentAmount": "0.00",
  "startDate": "2024-02-01",
  "endDate": "2024-02-29",
  "createdAt": "2024-01-20T15:45:00+00:00",
  "updatedAt": null,
  "progressPercentage": 0.0
}
```

### PATCH /api/budget-goals/{id} - Modifier un objectif

**Requête:**

```json
{
  "currentAmount": "300.00"
}
```

**Réponse (200 OK):**

```json
{
  "@id": "/api/budget-goals/1",
  "@type": "BudgetGoal",
  "id": 1,
  "label": "Budget mensuel janvier",
  "goalType": "monthly",
  "targetAmount": "500.00",
  "currentAmount": "300.00",
  "startDate": "2024-01-01",
  "endDate": "2024-01-31",
  "createdAt": "2024-01-01T10:00:00+00:00",
  "updatedAt": "2024-01-20T16:00:00+00:00",
  "progressPercentage": 60.0
}
```

## Champs de l'entité

| Champ | Type | Description | Requis |
|-------|------|-------------|--------|
| `id` | integer | Identifiant unique (auto-généré) | Non (lecture seule) |
| `label` | string(150) | Libellé de l'objectif | Oui |
| `goalType` | string(50) | Type d'objectif (ex: "monthly", "savings", "annual") | Oui |
| `targetAmount` | decimal(10,2) | Montant cible | Oui |
| `currentAmount` | decimal(10,2) | Montant actuel (défaut: 0.00) | Oui |
| `startDate` | date | Date de début (optionnel) | Non |
| `endDate` | date | Date de fin (optionnel) | Non |
| `createdAt` | datetime | Date de création (auto-généré) | Non (lecture seule) |
| `updatedAt` | datetime | Date de mise à jour (auto-généré) | Non (lecture seule) |
| `progressPercentage` | float | Pourcentage de progression calculé (0-100) | Non (calculé, lecture seule) |

## Codes d'erreur

- `401 Unauthorized` - Utilisateur non authentifié
- `403 Forbidden` - Tentative d'accès à un objectif d'un autre utilisateur
- `404 Not Found` - Objectif introuvable
- `422 Unprocessable Entity` - Erreur de validation

