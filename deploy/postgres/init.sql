-- Runs once, when the data volume is first created. The first migration also enables these
-- (P0-02), but a superuser is needed for CREATE EXTENSION and the app's role is not one — so the
-- extensions are here, and the migration's CREATE EXTENSION IF NOT EXISTS becomes a no-op.
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS citext;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
