#!/bin/bash
# HTTP smoke test: logs in as each role and requests every page the role may open.
BASE=${BASE:-http://127.0.0.1:8099}
fail=0
login() { # user
  local jar=$1; local user=$2
  rm -f "$jar"
  local tok=$(curl -s -c "$jar" "$BASE/login" | grep -o 'name="[^"]*csrf[^"]*" value="[^"]*"' | head -1)
  local name=$(echo "$tok" | sed 's/name="\([^"]*\)".*/\1/'); local val=$(echo "$tok" | sed 's/.*value="\([^"]*\)"/\1/')
  curl -s -o /dev/null -w "%{http_code}" -b "$jar" -c "$jar" -d "username=$user&password=Campus@2026&$name=$val" "$BASE/login"
}
check() { # jar path expected
  local code=$(curl -s -o /tmp/smoke_body -w "%{http_code}" -b "$1" "$BASE$2")
  local bad=""; grep -qiE "fatal error|warning:|notice:|deprecated:|stack trace|Uncaught|Undefined (variable|array key|index)" /tmp/smoke_body && bad=" PHP-ERROR"
  if [ "$code" != "$3" ] || [ -n "$bad" ]; then echo "FAIL $2 -> $code (want $3)$bad"; fail=1; fi
}
run_role() { # user "paths allowed" "paths denied"
  local jar=/tmp/jar_$1; echo "login $1 -> $(login $jar $1)"
  for p in $2; do check $jar $p 200; done
  for p in $3; do check $jar $p 403; done
}
run_role admin "/dashboard /students /enrollment /attendance /attendance/devices /grades /activities /services /notifications /insights /guidance /forecast /reports /users /audit /account" ""
run_role teacher "/teacher /students /attendance /grades /activities /notifications /insights /guidance /account" "/users /audit /enrollment /forecast /services /reports"
run_role student "/student /student/grades /student/requests /activities /notifications /account" "/students /users /audit /grades"
run_role guidance "/guidance /students /insights /notifications /account" "/users /audit /enrollment /dashboard /reports /grades"
exit $fail
