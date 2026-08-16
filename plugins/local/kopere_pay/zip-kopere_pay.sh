#!/usr/bin/env bash

find ./ -name "*.min.min.js" -depth -exec rm -v {} \;
find ./ -name ".DS_Store"    -depth -exec rm -v {} \;

cd ..
rm  -f kopere_pay/local_kopere_pay.zip
zip -r kopere_pay/local_kopere_pay.zip kopere_pay/ \
        --exclude=*sh --exclude=*ai \
        --exclude=*.git* --exclude=.gitignore \
        --exclude='kopere_pay/assets/*' \
        --exclude='kopere_pay/log/*' \
        --exclude='kopere_pay/lang/pt_br_uni/*' \
        --exclude='kopere_pay/lang/pt_br/*'

open kopere_pay/
