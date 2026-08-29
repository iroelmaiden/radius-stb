#!/bin/bash
CMD='/interface pppoe-server disconnect [find name=iroel]'
sshpass -p 'Kayangan119#' ssh -o StrictHostKeyChecking=no admin@172.16.2.1 "$CMD"
