-- DREK Meta 1.1.0: custom <link> elements (canonical, alternate, ...).
ALTER TABLE "#__drekmeta_items" ADD COLUMN "custom_links" text;
