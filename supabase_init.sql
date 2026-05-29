-- Supabase / Postgres schema for the OPay clone app
-- Run this with psql or the Supabase SQL editor

-- NOTE: This schema is derived from the PHP codebase and is a best-effort mapping.
-- Adjust types, constraints and defaults to your needs before running in production.

-- Users table
CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  uid TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  number TEXT,                       -- stored as 10-digit string
  device TEXT,
  email TEXT,
  date TIMESTAMP WITH TIME ZONE DEFAULT now(),
  password TEXT,                     -- password hash
  profile TEXT,                      -- URL/path to avatar
  android_id TEXT,
  plan TEXT DEFAULT 'free',
  subscription_date TEXT,            -- Stored as date string or '0'
  amount_in NUMERIC(14,2) DEFAULT 0,
  amount_out NUMERIC(14,2) DEFAULT 0,
  email_alert INTEGER DEFAULT 0,
  block INTEGER DEFAULT 0,
  balance NUMERIC(14,2) DEFAULT 0,
  pin_set INTEGER DEFAULT 0
);

-- History / Transactions
CREATE TABLE IF NOT EXISTS history (
  id BIGSERIAL PRIMARY KEY,
  accountname TEXT,
  accountnumber TEXT,
  bankname TEXT,
  amount NUMERIC(14,2) DEFAULT 0,
  narration TEXT,
  date3 TEXT,
  time TEXT,
  time1 TEXT,
  time3 TEXT,
  date1 TEXT,
  date2 TEXT,
  category TEXT,
  type TEXT,
  url TEXT,
  sid TEXT,
  status TEXT,
  tid TEXT,
  product_id TEXT,
  uid TEXT REFERENCES users(uid) ON DELETE SET NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_history_uid ON history(uid);
CREATE INDEX IF NOT EXISTS idx_history_product_id ON history(product_id);

-- Beneficiaries
CREATE TABLE IF NOT EXISTS beneficiary (
  id SERIAL PRIMARY KEY,
  accountname TEXT NOT NULL,
  accountnumber TEXT NOT NULL,
  bankname TEXT,
  url TEXT,
  uid TEXT REFERENCES users(uid) ON DELETE CASCADE,
  favorite TEXT DEFAULT 'false',
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_beneficiary_uid ON beneficiary(uid);
CREATE INDEX IF NOT EXISTS idx_beneficiary_accountnumber ON beneficiary(accountnumber);

-- Payment requests (uploads from `pay.php`)
CREATE TABLE IF NOT EXISTS payment_requests (
  id SERIAL PRIMARY KEY,
  request_id TEXT UNIQUE NOT NULL,
  uid TEXT REFERENCES users(uid) ON DELETE SET NULL,
  name TEXT,
  number TEXT,
  email TEXT,
  image TEXT,
  date TIMESTAMP WITH TIME ZONE DEFAULT now(),
  plan TEXT,
  status TEXT DEFAULT 'pending'
);

-- Bank details (single row expected)
CREATE TABLE IF NOT EXISTS bank_details (
  id SERIAL PRIMARY KEY,
  bank_name TEXT,
  account_name TEXT,
  account_number TEXT
);

-- Price / plan table used by admin & UI
CREATE TABLE IF NOT EXISTS price (
  id SERIAL PRIMARY KEY,
  type TEXT UNIQUE NOT NULL,
  price NUMERIC(12,2) DEFAULT 0
);

-- Admins (Admin-Maxwell folder)
CREATE TABLE IF NOT EXISTS admin (
  id SERIAL PRIMARY KEY,
  name TEXT,
  email TEXT UNIQUE NOT NULL,
  password TEXT NOT NULL
);

-- Maintenance mode table (single row expected)
CREATE TABLE IF NOT EXISTS maintenance (
  id SERIAL PRIMARY KEY,
  is_maintenance INTEGER DEFAULT 0
);

-- Notifications table for admin dashboard
CREATE TABLE IF NOT EXISTS notifications (
  id SERIAL PRIMARY KEY,
  icon TEXT DEFAULT 'fas fa-info-circle',
  title TEXT NOT NULL,
  message TEXT,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- App updates table for mobile updates
CREATE TABLE IF NOT EXISTS app_updates (
  id SERIAL PRIMARY KEY,
  version TEXT NOT NULL,
  message TEXT,
  url TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  release_date TIMESTAMP WITH TIME ZONE DEFAULT now()
);

-- Convenience: seed some price types if empty
INSERT INTO price (type, price)
SELECT * FROM (VALUES
  ('support',  0.00),
  ('weekly',  1000.00),
  ('monthly', 3000.00),
  ('lifetime', 10000.00),
  ('channel', 500.00)
) AS t(type, price)
WHERE NOT EXISTS (SELECT 1 FROM price WHERE type = t.type);

-- Seed maintenance status
INSERT INTO maintenance (id, is_maintenance)
VALUES (1, 0)
ON CONFLICT (id) DO NOTHING;

-- Seed bank details
INSERT INTO bank_details (id, bank_name, account_name, account_number)
VALUES (1, 'OPay Test Bank', 'Maxwell Admin Account', '9998887776')
ON CONFLICT (id) DO NOTHING;

-- Seed default admin if none exists
INSERT INTO admin (id, name, email, password)
VALUES (1, 'Admin Maxwell', 'admin@maxwell.com', 'admin123')
ON CONFLICT (id) DO NOTHING;

-- Seed default notifications
INSERT INTO notifications (id, title, message, icon)
VALUES 
  (1, 'System Setup Completed', 'The system database has been initialized successfully.', 'fas fa-check-circle'),
  (2, 'Welcome to Admin Panel', 'Manage user accounts, subscriptions, and system settings from this portal.', 'fas fa-user-shield')
ON CONFLICT (id) DO NOTHING;

-- Reset serial sequences after manual inserts
SELECT setval(pg_get_serial_sequence('maintenance', 'id'), COALESCE(MAX(id), 1)) FROM maintenance;
SELECT setval(pg_get_serial_sequence('bank_details', 'id'), COALESCE(MAX(id), 1)) FROM bank_details;
SELECT setval(pg_get_serial_sequence('admin', 'id'), COALESCE(MAX(id), 1)) FROM admin;
SELECT setval(pg_get_serial_sequence('notifications', 'id'), COALESCE(MAX(id), 1)) FROM notifications;

-- Make sure users.uid is indexed and unique (uid already unique)
CREATE INDEX IF NOT EXISTS idx_users_uid ON users(uid);

-- End of schema

