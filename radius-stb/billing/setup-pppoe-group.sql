INSERT IGNORE INTO radgroupreply (groupname, attribute, op, value) VALUES
('pppoe', 'Service-Type', ':=', 'Framed-User'),
('pppoe', 'Framed-Protocol', ':=', 'PPP'),
('pppoe', 'Mikrotik-Rate-Limit', ':=', '5M/5M'),
('pppoe', 'Simultaneous-Use', ':=', '1');
