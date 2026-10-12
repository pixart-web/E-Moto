SET NAMES utf8mb4;

-- A coluna role já aceita texto e não necessita de alteração estrutural.
-- Utilize o Painel Admin para escolher quem terá o cargo Boss.

INSERT INTO email_report_recipients (email, active) VALUES
('info@motogreen.pt', 1),
('marketing@motogreen.pt', 1),
('l.dalogli@motogreen.pt', 1)
ON DUPLICATE KEY UPDATE active = VALUES(active);
