# AI Prompts for Continent Module

Create a completly featured central database **Continent** module. The migration should include the following fields:

- `id`- primary key, auto-increment,
- `identifier`- unique UUID,
- `name`- unique string,
- `slug`- nullable string,
- `short_code`- nullable string, max length 10, indexed
- `iso_code`- nullable string, max length 10,
- `status`- boolean, default true, indexed
- `created_by`- unsignedBigInteger, (user id of the creator, nullable, No FK constraint),
- `updated_by`- unsignedBigInteger, (user id of the last updater, nullable, No FK constraint),
- `created_at`- timestamp,
- `updated_at`- timestamp,
- `deleted_at`- timestamp, nullable,

The model should have the following relationships:

- `countries`- has many `Country` model,
- `createdBy`- belongs to `User` model,
- `updatedBy`- belongs to `User` model,

Create a seeder to populate the continents table with realistic data of all the continents for all fields.

Update Postman collection and environment variables to include endpoints for the `Continent` module, including CRUD operations, any necessary relationships with the `Country` module, and ensure that the endpoints are properly documented with request (refer to `Requests/Central/Continent` for query parameters) and response examples.
