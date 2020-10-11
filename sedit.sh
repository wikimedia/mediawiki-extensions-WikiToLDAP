#!/bin/bash -ex

dir=`pwd`

oYourName=`jq -r ".[\"@metadata\"].authors|first" < i18n/qqq.json`
YourName=`git config user.name`

export oExt=`jq -r .name < extension.json`
export ext=`basename $dir`

export oUCFirst=${oExt,,}; oUCFirst=${oUCFirst^}
export UCFirst=${ext,,}; UCFirst=${UCFirst^}

export olcFirst=${oExt,}
export lcFirst=${ext,}

export olcAll=${oExt,,}
export lcAll=${ext,,}

ext() {
	newFile=`echo $1 | sed "s,$oExt,$ext,g"`
	git mv $1 $newFile
}
export -f ext

UCFirst() {
	newFile=`echo $1 | sed "s,$oUCFirst,$UCFirst,g"`
	git mv $1 $newFile
}
export -f UCFirst

lcFirst() {
	newFile=`echo $1 | sed "s,$olcFirst,$lcFirst,g"`
	git mv $1 $newFile
}
export -f lcFirst

lcAll() {
	newFile=`echo $1 | sed "s,$olcAll,$lcAll,g"`
	git mv $1 $newFile
}
export -f lcAll

printf "name\t\tReplacing\twith\n"
printf "%.*s\t%.*s\t%.*s\n" 8 "========" 8 "========" 8 "========"
printf "extension\t%s\t%s\n" "$oExt"		"$ext"
printf "UCFirst\t\t%s\t%s\n" "$oUCFirst"	"$UCFirst"
printf "lcFirst\t\t%s\t%s\n" "$olcFirst"	"$lcFirst"
printf "lcAll\t\t%s\t%s\n"   "$olcAll"		"$lcAll"
printf "\n"
printf "YourName\t%s\t%s\n"   "$oYourName"	"$YourName"
printf "\n"


echo -n "Make the above replacements [y/N]? "
while true; do
    read -sn1 response
    case "$response" in
        [yY]) echo 'y'; break;;
        [nN]|"") echo 'n'; exit 0;;
    esac
done


find . -depth -type d -name "*$oExt*" |
	xargs -r -i{} bash -c 'ext "{}"'
find . -depth -type f -name "*$oUCFirst*" |
	xargs -r -i{} bash -c 'UCFirst "{}"'
find . -depth -type f -name "*$olcFirst*" |
	xargs -r -i{} bash -c 'lcFirst "{}"'
find . -depth -type f -name "*$olcAll*" |
	xargs -r -i{} bash -c 'lcAll "{}"'

find . -depth -type d -name "*$oExt*" |
	xargs -r -i{} bash -c 'ext "{}"'
find . -depth -type d -name "*$oUCFirst*" |
	xargs -r -i{} bash -c 'UCFirst "{}"'
find . -depth -type d -name "*$olcFirst*" |
	xargs -r -i{} bash -c 'lcFirst "{}"'
find . -depth -type d -name "*$olcAll*" |
	xargs -r -i{} bash -c 'lcAll "{}"'

grep -R -l "$oYourName" . | grep -v ^./.git | grep -v ./vendor |
	xargs -r sed -i "s,$oYourName,$YourName,g"
grep -R -l "$oExt" . | grep -v ^./.git | grep -v ./vendor |
	xargs -r sed -i "s,$oExt,$ext,g"
grep -R -l "$oUCFirst" . | grep -v ^./.git | grep -v ./vendor |
	xargs -r sed -i "s,$oUCFirst,$UCFirst,g"
grep -R -l "$olcFirst" . | grep -v ^./.git | grep -v ./vendor |
	xargs -r sed -i "s,$olcFirst,$lcFirst,g"
grep -R -l "$olcAll" . | grep -v ^./.git | grep -v ./vendor |
	xargs -r sed -i "s,$olcAll,$lcAll,g"
