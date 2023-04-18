# This file contains an example template for using the Backup gem for backing up your Huginn installation to S3.

# In your crontab do something like:
# 0 0,12 * * * /bin/bash -l -c "cd /home/you/app/current && RAILS_ENV=production bundle exec backup perform -t huginn_backup --config_file /home/you/huginn_backup.rb" 2>&1 >> /home/you/huginn_backup_log.txt

# In backups.password on your server:
#   some password

# In huginn_backup.rb on your server put an edited version of the following file.  REMEMBER TO EDIT THE FILE!

database_yml = '/home/you/app/current/config/database.yml'
rails_env    = ENV['RAILS_ENV'] || 'production'

require 'yaml'
config = YAML.load_file(database_yml)

Backup::Model.new(:huginn_backup, 'The Huginn backup configuration') do

  split_into_chunks_of 4000

  database MySQL do |database|
    database.name               = config[rails_env]["database"]
    database.username           = config[rails_env]["username"]
    database.password           = config[rails_env]["password"]
    database.host               = config[rails_env]["host"]
    database.port               = config[rails_env]["port"]
    database.socket             = config[rails_env]["socket"]
    database.additional_options = ['--single-transaction', '--quick', '--hex-blob', '--add-drop-table']
  end

  encrypt_with OpenSSL do |encryption|
    encryption.password_file = "/home/you/backups.password"
    encryption.base64        = true
    encryption.salt          = true
  end

  compress_with Gzip do |compression|
    compression.level = 8
  end

  store_with S3 do |s3|
    s3.access_key_id      = 'YOUR_AWS_ACCESS_KEY'
    s3.secret_access_key  = 'YOUR_AWS_SECRET'
    s3.region             = 'us-east-1'
    s3.bucket             = 'my-huginn-backups'
    s3.keep               = 20
  end

  notify_by Mail do |mail|
    mail.on_success = false
    mail.on_warning = true
    mail.on_failure = true

    mail.from                 = 'you@example.com'
    mail.to                   = 'you@example.com'
    mail.address              = 'smtp.gmail.com'
    mail.domain               = "example.com"
    mail.user_name            = 'you@example.com'
    mail.password             = 'password'
    mail.port                 =  587
    mail.authentication       = "plain"
    mail.enable_starttls_auto = true
  end
end
