#!/usr/bin/env ruby

unless defined?(Rails)
  puts
  puts "Please run me with rails runner, for example:"
  puts "  RAILS_ENV=production bundle exec rails runner bin/twitter_stream.rb"
  puts
  exit 1
end

require 'cgi'
require 'json'
require 'twitter/json_stream'
require 'em-http-request'
require 'pp'

def stream!(filters, options = {}, &block)
  stream = Twitter::JSONStream.connect(
    :path    => "/1/statuses/#{(filters && filters.length > 0) ? 'filter' : 'sample'}.json#{"?track=#{filters.map {|f| CGI::escape(f) }.join(",")}" if filters && filters.length > 0}",
    :oauth   => {
      :consumer_key    => options[:consumer_key],
      :consumer_secret => options[:consumer_secret],
      :access_key      => options[:oauth_token] || options[:access_key],
      :access_secret   => options[:oauth_token_secret] || options[:access_secret]
    },
    :ssl     => true
  )

  stream.each_item do |status|
    status = JSON.parse(status) if status.is_a?(String)
    next unless status
    next if status.has_key?('delete')
    next unless status['text']
    status['text'] = status['text'].gsub(/&lt;/, "<").gsub(/&gt;/, ">").gsub(/[\t\n\r]/, '  ')
    block.call(status)
  end

  stream.on_error do |message|
    STDERR.puts " --> Twitter error: #{message} <--"
  end

  stream.on_no_data do |message|
    STDERR.puts " --> Got no data for awhile; trying to reconnect."
    EventMachine::stop_event_loop
  end

  stream.on_max_reconnects do |timeout, retries|
    STDERR.puts " --> Oops, tried too many times! <--"
    EventMachine::stop_event_loop
  end
end

def load_and_run(agents)
  agents.group_by { |agent| agent.options[:twitter_username] }.each do |twitter_username, agents|
    filter_to_agent_map = agents.map { |agent| agent.options[:filters] }.flatten.uniq.compact.map(&:strip).inject({}) { |m, f| m[f] = []; m }

    agents.each do |agent|
      agent.options[:filters].flatten.uniq.compact.map(&:strip).each do |filter|
        filter_to_agent_map[filter] << agent
      end
    end

    options = agents.first.options.slice(:consumer_key, :consumer_secret, :access_key, :oauth_token, :access_secret, :oauth_token_secret)

    recent_tweets = []

    stream!(filter_to_agent_map.keys, options) do |status|
      if status["retweeted_status"].present? && status["retweeted_status"].is_a?(Hash)
        puts "Skipping retweet: #{status["text"]}"
      elsif recent_tweets.include?(status["id_str"])
        puts "Skipping duplicate tweet: #{status["text"]}"
      else
        recent_tweets << status["id_str"]
        recent_tweets.shift if recent_tweets.length > DUPLICATE_DETECTION_LENGTH
        puts status["text"]
        filter_to_agent_map.keys.each do |filter|
          if (filter.downcase.split(SEPARATOR) - status["text"].downcase.split(SEPARATOR)).reject(&:empty?) == [] # Hacky McHackerson
            filter_to_agent_map[filter].each do |agent|
              puts " -> #{agent.name}"
              agent.process_tweet(filter, status)
            end
          end
        end
      end
    end
  end
end

RELOAD_TIMEOUT = 10.minutes
DUPLICATE_DETECTION_LENGTH = 1000
SEPARATOR = /[^\w_\-]+/

while true
  begin
    agents = Agents::TwitterStreamAgent.all

    EventMachine::run do
      EventMachine.add_periodic_timer(RELOAD_TIMEOUT) {
        puts "Reloading EventMachine and all Agents..."
        EventMachine::stop_event_loop
      }

      if agents.length == 0
        puts "No agents found.  Will look again in a minute."
        sleep 60
        EventMachine::stop_event_loop
      else
        puts "Found #{agents.length} agent(s).  Loading them now..."
        load_and_run agents
      end
    end

    print "Pausing..."; STDOUT.flush
    sleep 5
    puts "done."
  rescue SignalException, SystemExit
    EventMachine::stop_event_loop if EventMachine.reactor_running?
    exit
  rescue StandardError => e
    STDERR.puts "\nException #{e.message}:\n#{e.backtrace.join("\n")}\n\n"
    STDERR.puts "Waiting for a couple of minutes..."
    sleep 120
  end
end