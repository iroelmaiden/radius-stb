SELECT v.username, v.status, v.activated_at, v.duration_hours, v.time_limit,
CASE 
  WHEN v.status = 'used' AND v.activated_at IS NOT NULL THEN 
    ROUND((v.duration_hours * 3600 - TIMESTAMPDIFF(SECOND, v.activated_at, NOW())) / 3600, 1)
  WHEN v.status = 'available' THEN v.duration_hours
  ELSE 0
END as remaining_hours
FROM vouchers v 
WHERE v.status IN ('used', 'available') 
ORDER BY remaining_hours ASC;
