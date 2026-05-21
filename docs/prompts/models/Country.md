# AI Prompts for Country Module

/caveman Create a completly featured central database **Country** module. The migration should include the following fields:

- `id`- primary key, auto-increment,
- `identifier`- unique UUID,
- `name`- unique string,
- `slug`- nullable string,
- `continent_id`- foreign key to continents table, cascade on delete,
- `short_code`- nullable string, max length 10, indexed
- `iso_code`- nullable string, max length 10,
- `currency`- nullable string, max length 3, // Currency code (USD, EUR, GBP, etc.)
- `currency_sign`- nullable string, max length 5, // Currency symbol ($, etc.)
- `country_code`- nullable string, max length 10, // Phone country code (+254, +1, etc.)
- `phone_number_digits`- nullable unsigned tiny integer, // Number of digits in phone number
- `status`- boolean, default true, indexed
- `created_by`- foreign key to users table, nullable, null on delete,
- `updated_by`- foreign key to users table, nullable, null on delete,
- `created_at`- timestamp,
- `updated_at`- timestamp,
- `deleted_at`- timestamp, nullable,

The model should have the following relationships:

- `continent`- belongs to Continent model,
- `createdBy`- belongs to User model,
- `updatedBy`- belongs to User model,
