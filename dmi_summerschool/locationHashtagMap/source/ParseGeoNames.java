import java.io.BufferedWriter;
import java.io.FileInputStream;
import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.io.FileReader;
import java.io.FileWriter;
import java.io.IOException;
import java.io.OutputStreamWriter;
import java.io.PrintWriter;
import java.io.UnsupportedEncodingException;
import java.util.ArrayList;
import java.util.Enumeration;
import java.util.HashSet;
import java.util.Hashtable;
import java.util.Properties;

import au.com.bytecode.opencsv.CSVReader;
/*
 * 	Author: Lisa Madlberger, lisa.madlberger@tuwien.ac.at
 *  Vienna University of Technology, Institute for Software Engineering and Interactive Systems
 *  Date:  11.July 2014
 *  License: This work is licensed under a Creative Commons Attribution-NonCommercial 4.0 International License
 *  
 *  This Script was developed during the DMI Summer School 2014
 *  
 *  Description: 
 *  
 *  This class extracts location (given in a csv file, extracted from geonamens) mentioned in Tweets (given in a csv file exported from TCAT).
 *  
 *  Inputs: 
 *  Geonames-file: 
 *  Filename: Specify filepath in parseLocationsTweet.property file
 *  Fileformat: CSV
 *  Columns: (1) GeonamesId (2) Official name (3) Official name ascii (4) alternative names (separated by ,) (5) latitude (6) longitude (7) .. 
 *   *  Example Line 1847947	Shingū	Shingu	Schingu,Shingu,Shingui,Shingū,Sing,Singu,Singū,Синг	33.73333	135.98333	P	PPL	JP		43				31619		7	Asia/Tokyo	2012-01-19
 *  
 *  Tweet-file
 *  Filename: Specify filepath in parseLocationsTweet.property file
 *  Fileformat: CSV 
 *  Columns: (1) id	(2) time (unix) (3) created_at	(4) from_user_name	(5) from_user_lang	(6) text	(7) source	(7) location	(8) lat	(9) lng (10)...
 *
 *  Further Properties: 
 *  printHTML (1 - print HTML File showing Tweettexts and locations found highlighted / 0 - dont do that)
 *  printLocationHashtagMap=1 (1 - print LocationHashtagMap in inputfilename + "_LHM.csv" / 0)
 * printGPSLocationMap=1 ( 1 - print GPSLocationMap in file inputfilename + "_GPS.csv" / 0)
 * findLocationsInHashtag=1 (whether locations should be also found within hashtags --> turn off for hashtag-location map) 
 * noOfTweetToProcess=100 ( set a limit how many tweets should be processed 0 = no limit) 
 * 
 * Outputs:
 * 1) _result.html - showing Tweettexts and locations found highlighted in yellow 
 * 2) _LHM.csv - prints a line for each location mentioned in a tweet, and hashtags (separated by ';') co-occuring in that tweet
 * can be used to produce a Hashtag-Location Graph in Gephi
 * 3) _GPS.csv - prints a line for each location mentioned in a tweet (showing their id, name, lat, lng) and GPS coordinates assigned to the tweet, 
 * this file can be used to create a Location mentioned / GPS Location Graph in Gephi
 * (Check out the GeoLayout to produce graphs in geographic layout using actual location information (gps))
 */

public class ParseGeoNames {
    Hashtable<Integer, String> locationList = new Hashtable<Integer, String>();
    Hashtable<Integer, Double> latitudes = new Hashtable<Integer, Double>();
    Hashtable<Integer, Double> longitude = new Hashtable<Integer, Double>();

    long count_withoutLocation =0; 
    long count_numberOfLocationsFound =0;
    long count_totalTweets = 0;
    int maxCount = 0;
   
    String outputFile;
    String cityFile ;
    String inputFile ;
	int columnTweetText =5;
	int col_tweetId=0;
	int col_userId=2;
	int col_lat=9;
	int col_lng=10;
	int col_tweetTime=1;
    boolean printHighlight = true;
	boolean printLocationHashtagMap = true;
	boolean printMentionedLocationTweetGPSLocationMap = true;
	boolean findLocationInHashTag=true;

	 Hashtable<String, Integer> geonames;
	
	public static void main(String[] args) {

		System.out.println("GeonamesTweetParser started...");
		ParseGeoNames pg = new ParseGeoNames();
		pg.parseCSVFile();
	}	
	public ParseGeoNames() {


	}
	
	public void parseCSVFile()
	{
		try{
			readProperties();

			 printHeaders();
			 CSVReader tweetreader = new CSVReader(new FileReader(inputFile));
			 String[] tweet = tweetreader.readNext(); // skip headline
			 geonames = ReadCitiesFromCSV();	

			 while ((tweet = tweetreader.readNext()) != null && (count_totalTweets <maxCount || maxCount == 0) ) {

				count_totalTweets++;
				System.out.println("Processed Tweets: " + count_totalTweets);
			    Hashtable<Integer, String> placesFoundInTweet = getPlacesFromTweetText(tweet[columnTweetText]);
			
				Enumeration<Integer> i_placesFoundInTweet = placesFoundInTweet.keys();
				HashSet<Integer> uniqueLocationIds = new HashSet<Integer>();
				ArrayList<String> foundPlaceNames = new ArrayList<String>();

				if(i_placesFoundInTweet.hasMoreElements())
				{
					while(i_placesFoundInTweet.hasMoreElements())
					{
						int x = i_placesFoundInTweet.nextElement();
						String city = placesFoundInTweet.get(x);
						foundPlaceNames.add(city);
						uniqueLocationIds.add(geonames.get(city));						
					}
				}
				else 
					count_withoutLocation++;
				
				printLines(tweet, uniqueLocationIds, foundPlaceNames);
				count_numberOfLocationsFound+=uniqueLocationIds.size();
		} 	
			 tweetreader.close();
			
	}
	catch(Exception e)
	{
		e.printStackTrace();
	}
	//PRINT SUMMARY
	System.out.println("Total Tweets processed " + count_totalTweets + "\t Locations detected: " + count_numberOfLocationsFound + "\t Without Locations: " + count_withoutLocation );

	}
	private void readProperties()
	{
		try {
			Properties prop = new Properties();
			FileInputStream file = new FileInputStream("parseLocationTweet.properties");
			prop.load(file);
	        
			cityFile=prop.getProperty("filepathGeonamesFile");
	    	inputFile=prop.getProperty("filepathTweetFile"); 
	    	printHighlight = prop.getProperty("printHTML").equals("1") ? true : false;
	    	printLocationHashtagMap = prop.getProperty("printLocationHashtagMap").equals("1") ? true : false;
	    	printMentionedLocationTweetGPSLocationMap = prop.getProperty("printGPSLocationMap").equals("1") ? true : false;
	    	findLocationInHashTag=prop.getProperty("findLocationsInHashtag").equals("1") ? true : false;
	    	maxCount =Integer.parseInt(prop.getProperty("noOfTweetToProcess"));
	    	outputFile= inputFile.substring(0, inputFile.lastIndexOf("."));
	    	columnTweetText= Integer.parseInt(prop.getProperty("columnTweetText"));    	
	    	col_tweetId=Integer.parseInt(prop.getProperty("columnTweetId"));
	    	col_userId=Integer.parseInt(prop.getProperty("columnUserId"));
	    	col_lat=Integer.parseInt(prop.getProperty("columnLat"));
	    	col_lng=Integer.parseInt(prop.getProperty("columnLng"));
	    	col_tweetTime=Integer.parseInt(prop.getProperty("columnTweetTime"));
	    } catch (FileNotFoundException e) {
	    	e.printStackTrace();
	    } catch (IOException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
		System.out.println("Read properties...   ...OK");

	}
	private Hashtable<Integer, String> getPlacesFromTweetText(String tweet) throws UnsupportedEncodingException
	{
		
		 Enumeration<String> geonamesPlaces = geonames.keys();
		 String tweetText = new String(tweet.getBytes(),"UTF-8");
		 tweetText = tweetText.toLowerCase();
		 Hashtable<Integer, String> placedFoundInTweet = new Hashtable<Integer, String>();
		while(geonamesPlaces.hasMoreElements()) {
			String placeName = geonamesPlaces.nextElement();
			
			if(tweetText.contains(placeName))
			{
				int pos = tweetText.indexOf(placeName);					
				Enumeration<Integer> it = placedFoundInTweet.keys();
				if(it.hasMoreElements()) //if other places already found
				{
					while(it.hasMoreElements())
					{		
						int newEnd = pos + placeName.length();
						int newStart = pos;
						int oldStart = it.nextElement();
						String oldmatch = placedFoundInTweet.get(oldStart);
						int oldEnd = oldStart+oldmatch.length();
						//if overlap with found place
						if(newEnd >= oldStart &&  oldEnd >= newStart)
						{
							//overlap!
							if(placeName.length()>oldmatch.length()) // take the longest match
							{
								placedFoundInTweet.remove(oldStart);
								insertLocationHashtable(tweetText, pos, placeName, placedFoundInTweet);
							}
							
						}
						else //no overlap
						{
							insertLocationHashtable(tweetText, pos, placeName, placedFoundInTweet);
						}
					}
				}
				else //no places found yet --> insert
				{
					insertLocationHashtable(tweetText, pos, placeName, placedFoundInTweet);
				}
			}
		}
		return placedFoundInTweet;
	}
	private Hashtable<String, Integer> ReadCitiesFromCSV()
	{	    Hashtable<String, Integer> geonames = new Hashtable<String, Integer>();

		try{
		CSVReader reader = new CSVReader(new FileReader(cityFile), '\t');

  	    String [] nextLine;
		   
		    while ((nextLine = reader.readNext()) != null ) {
		    	
		    int id = Integer.parseInt(nextLine[0]);

		    String[] names = nextLine[3].split(",");
		    geonames.put(nextLine[1], id);
		    locationList.put(id, nextLine[1]);
		    latitudes.put(id, Double.parseDouble(nextLine[4]));
		    longitude.put(id, Double.parseDouble(nextLine[5]));

		    for(String n : names)
		    {
		    	if(n.matches("\\w+"))
		    	{
		    		if(n.length()>=3) //ignore latin names with less than 3 characters --> would cause too many false positives
		    			geonames.put(n.toLowerCase(), id);
		    	}
		    	else
		    	{
		    		if(n.length()>=2)
		    			geonames.put(n.toLowerCase(), id); //ignore non-latin names with less than 2 characters --> would cause too many false positives
		    	}
		    }
		    }
		    reader.close();
		}
		catch(Exception e) 
		{
			e.printStackTrace();
		}
		
		return geonames;
	}
	private void insertLocationHashtable(String tweet, int pos, String word, Hashtable<Integer, String> result)
	{
		String begin = tweet.substring(0, pos);
		int previousWhitespace = begin.lastIndexOf(' ') <0 ? 0:begin.lastIndexOf(' ');
		int previousAt = begin.lastIndexOf('@');
		int previousHash = begin.lastIndexOf('#');
		int previousUnderline = begin.lastIndexOf('_');
		int nextWhitespace = tweet.indexOf(' ', pos)<0 ? tweet.length() : tweet.indexOf(' ', pos);
		//int previousUrl = begin.lastIndexOf("http://");
	    
		if(word.matches("\\w+")) //if the found place is written in latin --> do more checks
		{
			//    if(previousWhitespace < previousUrl)
			//		System.out.println("word " + word + " URL " + tweet.substring(previousUrl, nextWhitespace));
		
			if(previousWhitespace<=previousHash) //match found within hashtag
			{
				if(findLocationInHashTag)
				{
				String hash = tweet.substring(previousHash+1, nextWhitespace);
			
				if(previousUnderline>previousHash)
				   hash=tweet.substring(previousUnderline+1, nextWhitespace);
				if(hash.length()==word.length())
					result.put(pos, word);
				}
			}
			else if(previousWhitespace<=previousAt) 
			{
				//match found in Username --> Ignore
			}
			else {
			String tweetpart = tweet.substring(previousWhitespace+1, nextWhitespace);
			if(tweetpart.length()== word.length())
				result.put(pos, word);
			}
		}	
		else
			result.put(pos,  word);
	}
	private ArrayList<String> getHashtags(String tweet)
	{
		ArrayList<String> hashtags = new ArrayList<String>();
		
		int pos =0;
		
		while(tweet.indexOf('#', pos)>0)
		{
			pos = tweet.indexOf('#', pos);
			int nextWhitespace = tweet.indexOf(' ', pos) > 0 ? tweet.indexOf(' ', pos): tweet.length();
			String hash= tweet.substring(pos, nextWhitespace);
			if(hash.contains("http:")) //ingore urls appended to hashtag
			{
				hash=hash.substring(0, hash.indexOf("http:"));
			}
			if(hash.contains("#")) //separate hashtags which stick together
			{
				String[] hashes = hash.split("#");
				for(String h : hashes)
					hashtags.add(h);
			}
			else
			{
				hashtags.add(hash);
			}

			pos++;
			
		}
		return hashtags;
		
	}
	
	

	/* PRINT HEADERS */
	void printHeaders()
	{
		if(printHighlight)
		printHighlightHTMLHeader();
		if(printLocationHashtagMap)
			printHeadLineLocationHashtagMap();
		if(printMentionedLocationTweetGPSLocationMap)
			printHeadLineMentionedLocationTweetGPSLocationMap();
		
		
	}
	void printHighlightHTMLHeader()
	{
		PrintWriter writer;
		try {
			writer = new PrintWriter(new FileWriter(getHighLightHTMLFileName(), false));
		    writer.println("<html><head><meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\"></head><body><table border=1>");
			writer.close();
		} catch (IOException e) {
			e.printStackTrace();
		}

	}
	void printHeadLineMentionedLocationTweetGPSLocationMap()
	{
		PrintWriter mapWriter;
		try {
			mapWriter = new PrintWriter(new FileWriter(getGPSOutputFileName(), false));
			mapWriter.println("locationId, locationLabel, locationLat, locationLng, tweetId, tweetTime, userId, tweetLat, tweetLng");
			mapWriter.close();
		} catch (IOException e) {
			e.printStackTrace();
		}
	}
	void printHeadLineLocationHashtagMap()
	{
		PrintWriter mapWriter;
		try {
			mapWriter = new PrintWriter(new FileWriter(getLocationHashtagOutputFileName(), false));
			mapWriter.println("hashtag, locationId, locationLabel, tweetId, tweetTime, userId, lat, lng");
			mapWriter.close();
		} catch (IOException e) {
			e.printStackTrace();
		}
	}

	void printLines(String[] tweet, HashSet<Integer> uniquePlaces, ArrayList<String> foundPlaces)
	{
		if(printHighlight)
			printHighlightHTMLLine(tweet[columnTweetText], foundPlaces);
		if(printLocationHashtagMap)
			printHashtagLocationMatch(tweet, getHashtags(tweet[columnTweetText]), uniquePlaces);
		if(printMentionedLocationTweetGPSLocationMap)
			printLocationMatch(tweet, uniquePlaces);
		
		
	}
	
	void printHighlightHTMLLine(String text, ArrayList<String> foundPlaces)
	{
		
          BufferedWriter writer;
		try {
			OutputStreamWriter streamwriter = new OutputStreamWriter(
	                new FileOutputStream(getHighLightHTMLFileName(), true), "UTF-8");
			 writer = new BufferedWriter(streamwriter);
			String output = text.toLowerCase();
			 for(String place : foundPlaces)
				 output = output.replace(place.toLowerCase(), "<font style=\"background-color: yellow;\">" + place +"</font>");
			 
			 
			 writer.write("<tr><td>"+output + "</tr></td>\n");
			 writer.close();
		} catch (IOException e) {
			e.printStackTrace();
		}		
	}
	void printHashtagLocationMatch(String[] tweet, ArrayList<String> hashtags, HashSet<Integer> locationIds)
	{
		try {
			if(hashtags.size()>0 && locationIds.size()>0)
			{
				PrintWriter mapWriter = new PrintWriter(new FileWriter(getLocationHashtagOutputFileName(), true));
			
			
				for(int l : locationIds)
					mapWriter.println(joinStrings(hashtags,";") + "," + l + ", " + locationList.get(l)+ ", "+tweet[col_tweetId]  + ", " + tweet[col_tweetTime]+ "," + tweet[col_userId] + ","  + latitudes.get(l) + "," + longitude.get(l) );
	
				mapWriter.close();
			}
		} catch (IOException e) {
			e.printStackTrace();
		}

	}
	void printLocationMatch(String[] tweet,  HashSet<Integer> locationIds)
	{
		try {
			if(locationIds.size()>0)
			{
			PrintWriter mapWriter = new PrintWriter(new FileWriter(getGPSOutputFileName(), true));
		
				for(int l : locationIds)
				{
					mapWriter.println( l + ", " + locationList.get(l)+ ", "+latitudes.get(l) + "," + longitude.get(l) + ", "+ tweet[col_tweetId]  + ", " + tweet[col_tweetTime]+ "," + tweet[col_userId] + "," + tweet[col_lat]+"," +tweet[col_lng]);
				}
				mapWriter.close();
			}
		} catch (IOException e) {
			e.printStackTrace();
		}

	}
	/* FOOTERS */
	void printFooters()
	{
		if(printHighlight)
		{
			printHighlightHTMLFooter();
		}
	}
	void printHighlightHTMLFooter()
	{
		PrintWriter writer;
		try {
			 writer = new PrintWriter(new FileWriter(getHighLightHTMLFileName(), true));
			 writer.println("</table></body></html>");
			 writer.close();
		} catch (IOException e) {
			e.printStackTrace();
		}
	}
	

	/* Support functions	 */
	String joinStrings(ArrayList<String> list, String delimiter)
	{
		String chain="";
		for(String h : list)
			if(h.length()>0)
				chain=chain+h + delimiter;
			
		return chain.substring(0, chain.length()-delimiter.length());
	}
	private String getGPSOutputFileName()
	{
		return outputFile + "_GPS.csv";
	}
	private String getLocationHashtagOutputFileName()
	{
		return outputFile + "_LHM.csv";
	}
	private String getHighLightHTMLFileName()
	{
		return outputFile + "_result.html";
	}
}


