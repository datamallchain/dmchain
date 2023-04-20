module Agents
  class TravelAgent < Agent

  cannot_receive_events!

	default_schedule "every_1d"

  description <<-MD
		Travel Agent will tell you about the minimum airline prices between a pair of cities, and between a certain period of time.
    Currency is USD, Please make sure difference between `start_date` and `end_date` is less than 150 days. You will need to contact [Adioso](http://adioso.com/)
		for `username` and `password`.
  MD

  event_description <<-MD

    If flights are present then events look like:
			{ "cost" : 75.23,
        "date" : "June 25, 2013",
			  "route" : "New York to Chicago" }

    otherwise
     { "nonetodest" : "No flights found to the specified destination" }
  MD

    def default_options
      {
        :start_date => Date.today.httpdate[0..15],
        :end_date   => Date.today.plus_with_duration(100).httpdate[0..15],
        :from       => "New York",
        :to         => "Chicago",
        :username   => "xx",
        :password   => "xx",
				:expected_update_period_in_days => "2"
      }
    end

    def working?
      (event = event_created_within(options[:expected_update_period_in_days].to_i.days)) && event.payload.present?
    end

    def validate_options
			errors.add(:base, "All fields are required") unless options[:start_date].present? && options[:end_date].present? && options[:from].present? && options[:to].present? && options[:username].present? && options[:password].present? && options[:expected_update_period_in_days].present?
		end

    def datetounixtime(date)
      date.to_time.to_i
    end

    def check
      auth_options = {:basic_auth => {:username =>options[:username], :password=>options[:password]}}
      parse_response = HTTParty.get "http://api.adioso.com/v2/search/parse?q=#{URI.encode(options[:from])}+to+#{URI.encode(options[:to])}", auth_options
      fare_request = parse_response["search_url"].gsub /(end=)(\d*)([^\d]*)(\d*)/, "\\1#{datetounixtime(options[:end_date])}\\3#{datetounixtime(options[:start_date])}"
      fare = HTTParty.get fare_request, auth_options
			unless fare["warnings"]
				event = fare["results"].min {|a,b| a["cost"] <=> b["cost"]}
				event["date"]  = Time.at(event["date"]).to_date.httpdate[0..15]
				event["route"] = "#{options[:from]} to #{options[:to]}" 
				create_event :payload => event
			else
				create_event :payload => fare["warnings"]
			end
				
    end
  end
end

