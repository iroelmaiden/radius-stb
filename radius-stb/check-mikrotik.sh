#!/usr/bin/env bash
echo y | plink -ssh -pw "1234Lima#" opencode@172.16.2.1 "/ip hotspot profile print detail"
