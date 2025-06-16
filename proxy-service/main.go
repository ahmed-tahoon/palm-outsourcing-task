package main

import (
	"log"
	"math/rand"
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
)

// Simple proxy list
var proxies = []string{
	"http://user1:pass1@proxy1.example.com:8080",
	"http://user2:pass2@proxy2.example.com:3128",
	"socks5://user3:pass3@proxy3.example.com:1080",
	"https://user4:pass4@proxy4.example.com:8888",
}

// Get all proxies
func GetProxies(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"proxies": proxies,
		"count":   len(proxies),
	})
}

// Get random proxy
func GetRandomProxy(c *gin.Context) {
	if len(proxies) == 0 {
		c.JSON(http.StatusServiceUnavailable, gin.H{"error": "No proxies available"})
		return
	}

	rand.Seed(time.Now().UnixNano())
	proxy := proxies[rand.Intn(len(proxies))]
	
	c.JSON(http.StatusOK, gin.H{"proxy": proxy})
}

func main() {
	router := gin.Default()
	
	router.GET("/proxies", GetProxies)
	router.GET("/proxy", GetRandomProxy)
	
	log.Println("Proxy service starting on :8080")
	router.Run(":8080")
} 