-- POSTGRESQL
CREATE SEQUENCE site_id_seq START 1;
CREATE TABLE site (
	id BIGINT DEFAULT nextval('site_id_seq'),
	url VARCHAR(255) NOT NULL,
  site_data TEXT DEFAULT '', -- store JSON, with detected apps
  headers TEXT DEFAULT '' , -- raw response headers text
  html TEXT DEFAULT '', -- raw response html
	CONSTRAINT site_id PRIMARY KEY (id)
);

