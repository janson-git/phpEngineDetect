-- POSTGRESQL
CREATE SEQUENCE site_id_seq START 1;
CREATE TABLE site (
	id BIGINT DEFAULT nextval('site_id_seq'),
	url VARCHAR(255) NOT NULL,
  site_data TEXT DEFAULT '',
	CONSTRAINT site_id PRIMARY KEY (id)
);

-- 
-- CREATE SEQUENCE site_data_seq START 1;
-- CREATE TABLE site_data (
-- 	id BIGINT DEFAULT nextval('site_data_seq'),
-- 	site_id BIGINT,
-- 	category_id INTEGER,
-- 	CONSTRAINT site_data_id_key PRIMARY KEY (id)	
-- );
-- 
-- CREATE INDEX ON site_data (site_id);
-- CREATE INDEX ON site_data (category_id);