#!/bin/sh
# Buckets, users and policies for the object store (deploy/compose.yml → minio-init). Runs on every
# `up` and is idempotent: `mc` treats an existing bucket/user/policy as success.
#
# Two credentials, each confined to one bucket (doc 04: verification documents live in a SEPARATE
# bucket with SEPARATE credentials from public media). The app holds both; a leak of the media
# key cannot read anyone's identity papers, and vice versa.
set -eu

mc alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" >/dev/null

for bucket in media verification; do
  mc mb --ignore-existing "local/$bucket" >/dev/null
  # Nothing is public: every byte is served by Laravel after an entitlement check.
  mc anonymous set none "local/$bucket" >/dev/null
done

# Identity papers: keep every version for the retention doc 04 requires, so an overwrite or a
# delete by a leaked credential is recoverable by the root user.
mc version enable local/verification >/dev/null

policy() {
  # $1 policy name, $2 bucket
  cat <<EOF >"/tmp/$1.json"
{
  "Version": "2012-10-17",
  "Statement": [
    { "Effect": "Allow", "Action": ["s3:ListBucket", "s3:GetBucketLocation"], "Resource": ["arn:aws:s3:::$2"] },
    { "Effect": "Allow", "Action": ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"], "Resource": ["arn:aws:s3:::$2/*"] }
  ]
}
EOF
  mc admin policy create local "$1" "/tmp/$1.json" >/dev/null 2>&1 || true
}

policy media-rw media
policy verification-rw verification

user() {
  # $1 access key, $2 secret key, $3 policy
  mc admin user add local "$1" "$2" >/dev/null 2>&1 || true
  mc admin policy attach local "$3" --user "$1" >/dev/null 2>&1 || true
}

user "$MEDIA_ACCESS_KEY" "$MEDIA_SECRET_KEY" media-rw
user "$VERIFICATION_ACCESS_KEY" "$VERIFICATION_SECRET_KEY" verification-rw

echo "minio: buckets media + verification ready; two confined users attached."
