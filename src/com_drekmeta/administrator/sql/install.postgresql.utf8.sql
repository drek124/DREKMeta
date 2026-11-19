CREATE TABLE IF NOT EXISTS "#__drekmeta_items" (
  "id" serial NOT NULL,
  "menu_item_id" integer NOT NULL DEFAULT 0,
  "title" varchar(255) NOT NULL DEFAULT '',
  "og_title" varchar(255) DEFAULT NULL,
  "og_description" text,
  "og_image" varchar(1024) DEFAULT NULL,
  "og_image_alt" varchar(255) DEFAULT NULL,
  "og_type" varchar(50) DEFAULT NULL,
  "og_site_name" varchar(255) DEFAULT NULL,
  "twitter_card" varchar(30) DEFAULT NULL,
  "theme_color" varchar(7) DEFAULT NULL,
  "custom_tags" text,
  "component_embed_enabled" smallint NOT NULL DEFAULT 0,
  "component_embed" text,
  "state" smallint NOT NULL DEFAULT 1,
  "created" timestamp without time zone NOT NULL,
  "created_by" integer NOT NULL DEFAULT 0,
  "modified" timestamp without time zone NOT NULL,
  "modified_by" integer NOT NULL DEFAULT 0,
  "checked_out" integer,
  "checked_out_time" timestamp without time zone,
  PRIMARY KEY ("id"),
  CONSTRAINT "#__drekmeta_items_idx_menu_item_id" UNIQUE ("menu_item_id")
);

CREATE INDEX "#__drekmeta_items_idx_state" ON "#__drekmeta_items" ("state");
CREATE INDEX "#__drekmeta_items_idx_checkout" ON "#__drekmeta_items" ("checked_out");

INSERT INTO "#__drekmeta_items" ("menu_item_id", "title", "og_type", "twitter_card", "component_embed_enabled", "state", "created", "modified")
VALUES (0, 'Global defaults (all pages)', 'website', 'summary_large_image', 0, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON CONFLICT ("menu_item_id") DO NOTHING;
