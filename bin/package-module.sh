#!/usr/bin/env bash

rm -rf build
mkdir -p build/gamepanelio

cp -R src/* build/gamepanelio
cp -R vendor build/gamepanelio

cp LICENSE build/LICENSE.txt
cp instructions.txt build/

cd build
zip -r gamepanelio.zip * -x *.git*
