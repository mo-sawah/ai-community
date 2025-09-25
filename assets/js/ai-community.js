/**
 * AI Community Frontend JavaScript
 */
(function ($) {
  "use strict";

  // Global state
  const AICommunity = {
    currentUser: aiCommunityData.currentUser || null,
    settings: aiCommunityData.settings || {},
    currentPage: "home",
    currentTab: "hot",
    posts: [],
    votedPosts: JSON.parse(localStorage.getItem("ai_community_votes") || "{}"),
    showMobileMenu: false,
    loading: false,
  };

  // Initialize when DOM is ready
  $(document).ready(function () {
    init();
  });

  /**
   * Initialize the application
   */
  function init() {
    // Check if we're on a community page
    const container = $("#ai-community-app");
    if (container.length === 0) return;

    // Apply settings
    applySettings();

    // Bind events
    bindEvents();

    // Load initial content
    loadPosts();

    // Initialize features
    initializeVoting();
    initializeComments();
    initializeSearch();
    initializeMobileMenu();

    // Render initial state
    renderApp();
  }

  /**
   * Apply user settings to the interface
   */
  function applySettings() {
    const { settings } = AICommunity;

    // Apply colors
    if (settings.primary_color) {
      document.documentElement.style.setProperty(
        "--ai-primary-color",
        settings.primary_color
      );
    }
    if (settings.secondary_color) {
      document.documentElement.style.setProperty(
        "--ai-secondary-color",
        settings.secondary_color
      );
    }

    // Apply font family
    if (settings.font_family && settings.font_family !== "system") {
      $("body").addClass(`font-${settings.font_family}`);
    }

    // Apply layout class
    if (settings.layout_type) {
      $("#ai-community-app").addClass(`layout-${settings.layout_type}`);
    }
  }

  /**
   * Bind all event listeners
   */
  function bindEvents() {
    // Navigation events
    $(document).on("click", '[data-action="navigate"]', handleNavigation);
    $(document).on("click", '[data-action="change-tab"]', handleTabChange);

    // Voting events
    $(document).on("click", '[data-action="vote"]', handleVote);

    // Comment events
    $(document).on("click", '[data-action="toggle-comments"]', toggleComments);
    $(document).on("submit", ".comment-form", handleCommentSubmit);

    // Form events
    $(document).on("submit", "#login-form", handleLogin);
    $(document).on("submit", "#register-form", handleRegister);
    $(document).on("submit", "#create-post-form", handleCreatePost);

    // Search events
    $(document).on("input", "#search-input", debounce(handleSearch, 300));

    // Mobile menu
    $(document).on(
      "click",
      '[data-action="toggle-mobile-menu"]',
      toggleMobileMenu
    );

    // Modal events
    $(document).on("click", ".modal-backdrop, .modal-close", closeModals);

    // Infinite scroll
    $(window).on("scroll", debounce(handleScroll, 100));

    // Keyboard navigation
    $(document).on("keydown", handleKeyboardNavigation);
  }

  /**
   * Load posts from the server
   */
  function loadPosts(params = {}) {
    if (AICommunity.loading) return;

    const defaultParams = {
      page: 1,
      per_page: AICommunity.settings.posts_per_page || 10,
      sort: AICommunity.currentTab,
    };

    const requestParams = Object.assign(defaultParams, params);
    AICommunity.loading = true;

    showLoadingSpinner();

    $.ajax({
      url: aiCommunityData.restUrl + "posts",
      method: "GET",
      data: requestParams,
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", aiCommunityData.restNonce);
      },
    })
      .done(function (posts) {
        if (requestParams.page === 1) {
          AICommunity.posts = posts;
        } else {
          AICommunity.posts = AICommunity.posts.concat(posts);
        }
        renderPosts();
      })
      .fail(function (xhr) {
        console.error("Failed to load posts:", xhr);
        showNotification(aiCommunityData.translations.error, "error");
      })
      .always(function () {
        AICommunity.loading = false;
        hideLoadingSpinner();
      });
  }

  /**
   * Render the main application
   */
  function renderApp() {
    const container = $("#ai-community-app");

    if (AICommunity.currentPage === "home") {
      renderHomePage(container);
    } else if (AICommunity.currentPage === "login") {
      renderLoginPage(container);
    } else if (AICommunity.currentPage === "register") {
      renderRegisterPage(container);
    } else if (AICommunity.currentPage === "create-post") {
      renderCreatePostPage(container);
    }
  }

  /**
   * Render home page
   */
  function renderHomePage(container) {
    const html = `
            <div class="ai-community-header">
                ${renderNavigation()}
            </div>
            <div class="ai-community-main">
                <div class="ai-community-container">
                    ${renderSortTabs()}
                    <div class="ai-community-content-wrapper">
                        <div class="ai-community-posts" id="posts-container">
                            <!-- Posts will be loaded here -->
                        </div>
                        ${
                          AICommunity.settings.layout_type === "sidebar"
                            ? renderSidebar()
                            : ""
                        }
                    </div>
                </div>
            </div>
        `;

    container.html(html);
    loadPosts();
  }

  /**
   * Render navigation
   */
  function renderNavigation() {
    return `
            <nav class="ai-community-nav">
                <div class="nav-brand">
                    <h1 class="nav-title">AI Community</h1>
                </div>
                <div class="nav-search">
                    <input type="text" id="search-input" placeholder="${
                      aiCommunityData.translations.search_placeholder ||
                      "Search posts, communities..."
                    }">
                    <button type="button" class="search-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="m21 21-4.35-4.35"/>
                        </svg>
                    </button>
                </div>
                <div class="nav-actions">
                    ${renderNavActions()}
                </div>
                <button class="mobile-menu-toggle" data-action="toggle-mobile-menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"/>
                        <line x1="3" y1="12" x2="21" y2="12"/>
                        <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
            </nav>
        `;
  }

  /**
   * Render navigation actions
   */
  function renderNavActions() {
    if (AICommunity.currentUser) {
      return `
                <button class="btn btn-primary" data-action="navigate" data-page="create-post">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"/>
                        <line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Create Post
                </button>
                <div class="user-menu">
                    <div class="user-avatar">${getUserInitials(
                      AICommunity.currentUser.name
                    )}</div>
                    <div class="user-info">
                        <div class="user-name">${
                          AICommunity.currentUser.name
                        }</div>
                        <div class="user-karma">${
                          AICommunity.currentUser.karma || 0
                        } karma</div>
                    </div>
                </div>
            `;
    } else {
      return `
                <button class="btn btn-outline" data-action="navigate" data-page="login">Sign In</button>
                <button class="btn btn-primary" data-action="navigate" data-page="register">Sign Up</button>
            `;
    }
  }

  /**
   * Render sort tabs
   */
  function renderSortTabs() {
    const tabs = [
      { id: "hot", label: "Hot", icon: "trending-up" },
      { id: "new", label: "New", icon: "clock" },
      { id: "top", label: "Top", icon: "star" },
      { id: "rising", label: "Rising", icon: "arrow-up" },
    ];

    const tabsHtml = tabs
      .map(
        (tab) => `
            <button class="sort-tab ${
              tab.id === AICommunity.currentTab ? "active" : ""
            }"
                    data-action="change-tab" data-tab="${tab.id}">
                ${getIcon(tab.icon)}
                ${tab.label}
            </button>
        `
      )
      .join("");

    return `<div class="sort-tabs">${tabsHtml}</div>`;
  }

  /**
   * Render posts
   */
  function renderPosts() {
    const container = $("#posts-container");

    if (!AICommunity.posts || AICommunity.posts.length === 0) {
      container.html('<div class="no-posts">No posts found.</div>');
      return;
    }

    const postsHtml = AICommunity.posts
      .map((post) => renderPost(post))
      .join("");
    container.html(postsHtml);
  }

  /**
   * Render single post
   */
  function renderPost(post) {
    const userVote = AICommunity.votedPosts[post.id];
    const currentVotes =
      parseInt(post.votes) +
      (userVote === "up" ? 1 : userVote === "down" ? -1 : 0);
    const timeAgo = formatTimeAgo(post.created_at);
    const tags = Array.isArray(post.tags_array) ? post.tags_array : [];

    return `
            <article class="post-card" data-post-id="${post.id}">
                <div class="post-voting">
                    <button class="vote-btn vote-up ${
                      userVote === "up" ? "voted" : ""
                    }" 
                            data-action="vote" data-post-id="${
                              post.id
                            }" data-vote="up"
                            ${!AICommunity.currentUser ? "disabled" : ""}>
                        ${getIcon("chevron-up")}
                    </button>
                    <span class="vote-count ${
                      currentVotes > 0
                        ? "positive"
                        : currentVotes < 0
                        ? "negative"
                        : ""
                    }">
                        ${formatNumber(currentVotes)}
                    </span>
                    <button class="vote-btn vote-down ${
                      userVote === "down" ? "voted" : ""
                    }" 
                            data-action="vote" data-post-id="${
                              post.id
                            }" data-vote="down"
                            ${!AICommunity.currentUser ? "disabled" : ""}>
                        ${getIcon("chevron-down")}
                    </button>
                </div>
                
                <div class="post-content">
                    <div class="post-meta">
                        <span class="community">c/${post.community}</span>
                        <span class="separator">•</span>
                        <span class="author">u/${post.author_name}</span>
                        ${
                          post.is_ai_generated == "1"
                            ? `
                            <span class="separator">•</span>
                            <span class="ai-badge">
                                ${getIcon("bot")}
                                AI
                            </span>
                        `
                            : ""
                        }
                        <span class="separator">•</span>
                        <time>${timeAgo}</time>
                    </div>
                    
                    <h2 class="post-title">${escapeHtml(post.title)}</h2>
                    
                    <div class="post-excerpt">
                        ${escapeHtml(
                          post.excerpt || post.content.substring(0, 200) + "..."
                        )}
                    </div>
                    
                    ${
                      tags.length > 0
                        ? `
                        <div class="post-tags">
                            ${tags
                              .map(
                                (tag) =>
                                  `<span class="tag">${escapeHtml(
                                    tag.trim()
                                  )}</span>`
                              )
                              .join("")}
                        </div>
                    `
                        : ""
                    }
                    
                    <div class="post-actions">
                        <button class="action-btn" data-action="toggle-comments" data-post-id="${
                          post.id
                        }">
                            ${getIcon("message-circle")}
                            ${post.comment_count || 0} comments
                        </button>
                        <button class="action-btn" data-action="share" data-post-id="${
                          post.id
                        }">
                            ${getIcon("share")}
                            Share
                        </button>
                        <button class="action-btn" data-action="save" data-post-id="${
                          post.id
                        }">
                            ${getIcon("bookmark")}
                            Save
                        </button>
                    </div>
                    
                    <div class="comments-section" id="comments-${
                      post.id
                    }" style="display: none;">
                        <!-- Comments will be loaded here -->
                    </div>
                </div>
            </article>
        `;
  }

  /**
   * Render sidebar
   */
  function renderSidebar() {
    return `
            <aside class="ai-community-sidebar">
                <div class="sidebar-widget">
                    <h3>About Community</h3>
                    <p>An AI-powered community platform that generates engaging discussions.</p>
                    <div class="community-stats">
                        <div class="stat-item">
                            <span class="stat-value">12.4k</span>
                            <span class="stat-label">members</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value">342</span>
                            <span class="stat-label">online</span>
                        </div>
                    </div>
                </div>
                
                <div class="sidebar-widget">
                    <h3>Popular Communities</h3>
                    <div class="communities-list">
                        ${renderCommunities()}
                    </div>
                </div>
                
                <div class="sidebar-widget">
                    <h3>
                        ${getIcon("bot")}
                        AI Activity
                    </h3>
                    <div class="ai-stats">
                        <div class="stat-row">
                            <span>Posts generated today:</span>
                            <span class="stat-value">5</span>
                        </div>
                        <div class="stat-row">
                            <span>Replies generated:</span>
                            <span class="stat-value">23</span>
                        </div>
                        <div class="stat-row">
                            <span>Last scan:</span>
                            <span class="stat-value">2h ago</span>
                        </div>
                    </div>
                </div>
            </aside>
        `;
  }

  /**
   * Render communities list
   */
  function renderCommunities() {
    const communities = [
      { name: "general", members: "8.2k", color: "#6b7280" },
      { name: "development", members: "5.3k", color: "#10b981" },
      { name: "ai", members: "4.2k", color: "#8b5cf6" },
      { name: "announcements", members: "2.1k", color: "#3b82f6" },
      { name: "help", members: "1.8k", color: "#ef4444" },
    ];

    return communities
      .map(
        (community) => `
            <div class="community-item" data-community="${community.name}">
                <div class="community-dot" style="background-color: ${community.color};"></div>
                <span class="community-name">c/${community.name}</span>
                <span class="community-members">${community.members}</span>
            </div>
        `
      )
      .join("");
  }

  /**
   * Render login page
   */
  function renderLoginPage(container) {
    const html = `
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h2>Sign in to AI Community</h2>
                        <p>Join our AI-powered discussions</p>
                    </div>
                    
                    <form id="login-form" class="auth-form">
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <input type="email" id="login-email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <input type="password" id="login-password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">
                            Sign In
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>Don't have an account? 
                           <a href="#" data-action="navigate" data-page="register">Register here</a>
                        </p>
                    </div>
                </div>
            </div>
        `;

    container.html(html);
  }

  /**
   * Render register page
   */
  function renderRegisterPage(container) {
    const html = `
            <div class="auth-container">
                <div class="auth-card">
                    <div class="auth-header">
                        <h2>Join AI Community</h2>
                        <p>Create your account and start discussing</p>
                    </div>
                    
                    <form id="register-form" class="auth-form">
                        <div class="form-group">
                            <label for="register-name">Full Name</label>
                            <input type="text" id="register-name" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="register-username">Username</label>
                            <input type="text" id="register-username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="register-email">Email</label>
                            <input type="email" id="register-email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="register-password">Password</label>
                            <input type="password" id="register-password" name="password" required minlength="6">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full">
                            Create Account
                        </button>
                    </form>
                    
                    <div class="auth-footer">
                        <p>Already have an account? 
                           <a href="#" data-action="navigate" data-page="login">Sign in</a>
                        </p>
                    </div>
                </div>
            </div>
        `;

    container.html(html);
  }

  /**
   * Render create post page
   */
  function renderCreatePostPage(container) {
    if (!AICommunity.currentUser) {
      AICommunity.currentPage = "login";
      renderLoginPage(container);
      return;
    }

    const html = `
            <div class="create-post-container">
                <div class="page-header">
                    <h1>Create New Post</h1>
                    <button class="btn btn-outline" data-action="navigate" data-page="home">
                        ${getIcon("arrow-left")}
                        Back to Community
                    </button>
                </div>
                
                <form id="create-post-form" class="create-post-form">
                    <div class="form-group">
                        <label for="post-title">Title</label>
                        <input type="text" id="post-title" name="title" 
                               placeholder="What's your post about?" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="post-community">Community</label>
                        <select id="post-community" name="community">
                            <option value="general">General</option>
                            <option value="development">Development</option>
                            <option value="ai">AI & Machine Learning</option>
                            <option value="announcements">Announcements</option>
                            <option value="help">Help & Support</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="post-content">Content</label>
                        <textarea id="post-content" name="content" rows="10"
                                  placeholder="Share your thoughts, ask questions, or start a discussion..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="post-tags">Tags (comma-separated)</label>
                        <input type="text" id="post-tags" name="tags" 
                               placeholder="javascript, tutorial, beginner">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            ${getIcon("send")}
                            Post
                        </button>
                        <button type="button" class="btn btn-outline" data-action="navigate" data-page="home">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        `;

    container.html(html);
    initializeEditor();
  }

  // Event Handlers

  /**
   * Handle navigation
   */
  function handleNavigation(e) {
    e.preventDefault();
    const page = $(this).data("page");
    navigateTo(page);
  }

  /**
   * Handle tab change
   */
  function handleTabChange(e) {
    e.preventDefault();
    const tab = $(this).data("tab");

    AICommunity.currentTab = tab;
    $(".sort-tab").removeClass("active");
    $(this).addClass("active");

    loadPosts({ page: 1, sort: tab });
  }

  /**
   * Handle voting
   */
  function handleVote(e) {
    e.preventDefault();

    if (!AICommunity.currentUser) {
      showNotification(
        aiCommunityData.translations.vote_login_required ||
          "Please login to vote",
        "warning"
      );
      return;
    }

    const postId = $(this).data("post-id");
    const voteType = $(this).data("vote");

    vote(postId, voteType);
  }

  /**
   * Handle comment toggle
   */
  function toggleComments(e) {
    e.preventDefault();
    const postId = $(this).data("post-id");
    const commentsContainer = $(`#comments-${postId}`);

    if (commentsContainer.is(":visible")) {
      commentsContainer.slideUp();
    } else {
      commentsContainer.slideDown();
      loadComments(postId);
    }
  }

  /**
   * Handle form submissions
   */
  function handleLogin(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    // Mock login for demo - in real implementation, use WordPress authentication
    showNotification("Logging in...", "info");

    setTimeout(() => {
      AICommunity.currentUser = {
        id: 1,
        name: "Demo User",
        username: "demo_user",
        karma: 250,
      };

      navigateTo("home");
      showNotification("Successfully logged in!", "success");
    }, 1000);
  }

  function handleRegister(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    // Mock registration
    showNotification("Creating account...", "info");

    setTimeout(() => {
      AICommunity.currentUser = {
        id: 2,
        name: formData.get("name"),
        username: formData.get("username"),
        karma: 0,
      };

      navigateTo("home");
      showNotification("Account created successfully!", "success");
    }, 1000);
  }

  function handleCreatePost(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    const postData = {
      title: formData.get("title"),
      content: formData.get("content"),
      community: formData.get("community"),
      tags: formData.get("tags"),
    };

    createPost(postData);
  }

  function handleSearch(e) {
    const query = $(e.target).val();

    if (query.length > 2) {
      searchPosts(query);
    } else if (query.length === 0) {
      loadPosts({ page: 1 });
    }
  }

  function handleScroll() {
    if (AICommunity.loading) return;

    const scrollTop = $(window).scrollTop();
    const windowHeight = $(window).height();
    const documentHeight = $(document).height();

    // Load more posts when near bottom
    if (scrollTop + windowHeight > documentHeight - 1000) {
      const nextPage =
        Math.floor(
          AICommunity.posts.length / (AICommunity.settings.posts_per_page || 10)
        ) + 1;
      loadPosts({ page: nextPage });
    }
  }

  function handleKeyboardNavigation(e) {
    // Escape key closes modals
    if (e.key === "Escape") {
      closeModals();
      if (AICommunity.showMobileMenu) {
        toggleMobileMenu();
      }
    }

    // Arrow keys for post navigation (when focused)
    if (e.target.classList.contains("post-card")) {
      if (e.key === "ArrowDown") {
        const next = $(e.target).next(".post-card")[0];
        if (next) next.focus();
      } else if (e.key === "ArrowUp") {
        const prev = $(e.target).prev(".post-card")[0];
        if (prev) prev.focus();
      }
    }
  }

  // Core Functions

  /**
   * Navigate to a page
   */
  function navigateTo(page) {
    AICommunity.currentPage = page;
    renderApp();

    // Update URL without page reload
    const newUrl =
      window.location.pathname + (page !== "home" ? "#" + page : "");
    window.history.pushState({ page: page }, "", newUrl);
  }

  /**
   * Vote on a post
   */
  function vote(postId, voteType) {
    const currentVote = AICommunity.votedPosts[postId];

    // Optimistic update
    if (currentVote === voteType) {
      delete AICommunity.votedPosts[postId];
    } else {
      AICommunity.votedPosts[postId] = voteType;
    }

    // Save to localStorage
    localStorage.setItem(
      "ai_community_votes",
      JSON.stringify(AICommunity.votedPosts)
    );

    // Update UI
    updateVoteDisplay(postId);

    // Send to server
    $.ajax({
      url: aiCommunityData.restUrl + `posts/${postId}/vote`,
      method: "POST",
      data: { vote_type: voteType },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", aiCommunityData.restNonce);
      },
    }).fail(function () {
      // Revert on failure
      if (currentVote) {
        AICommunity.votedPosts[postId] = currentVote;
      } else {
        delete AICommunity.votedPosts[postId];
      }
      updateVoteDisplay(postId);
      localStorage.setItem(
        "ai_community_votes",
        JSON.stringify(AICommunity.votedPosts)
      );
      showNotification("Failed to record vote", "error");
    });
  }

  /**
   * Update vote display
   */
  function updateVoteDisplay(postId) {
    const post = AICommunity.posts.find((p) => p.id == postId);
    if (!post) return;

    const userVote = AICommunity.votedPosts[postId];
    const currentVotes =
      parseInt(post.votes) +
      (userVote === "up" ? 1 : userVote === "down" ? -1 : 0);

    const postElement = $(`.post-card[data-post-id="${postId}"]`);
    const voteCount = postElement.find(".vote-count");
    const upBtn = postElement.find(".vote-up");
    const downBtn = postElement.find(".vote-down");

    voteCount.text(formatNumber(currentVotes));
    voteCount.removeClass("positive negative");
    if (currentVotes > 0) voteCount.addClass("positive");
    else if (currentVotes < 0) voteCount.addClass("negative");

    upBtn.removeClass("voted");
    downBtn.removeClass("voted");

    if (userVote === "up") upBtn.addClass("voted");
    else if (userVote === "down") downBtn.addClass("voted");
  }

  /**
   * Create a new post
   */
  function createPost(postData) {
    showNotification("Creating post...", "info");

    $.ajax({
      url: aiCommunityData.restUrl + "posts",
      method: "POST",
      data: postData,
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", aiCommunityData.restNonce);
      },
    })
      .done(function () {
        navigateTo("home");
        loadPosts({ page: 1 });
        showNotification("Post created successfully!", "success");
      })
      .fail(function () {
        showNotification("Failed to create post", "error");
      });
  }

  /**
   * Search posts
   */
  function searchPosts(query) {
    loadPosts({ page: 1, search: query });
  }

  /**
   * Load comments for a post
   */
  function loadComments(postId) {
    const container = $(`#comments-${postId}`);

    if (container.find(".comments-list").length > 0) {
      return; // Already loaded
    }

    container.html(`
            ${
              AICommunity.currentUser
                ? `
                <div class="comment-form">
                    <textarea placeholder="Write a reply..." rows="3"></textarea>
                    <button class="btn btn-primary btn-sm" onclick="addComment(${postId})">Reply</button>
                </div>
            `
                : ""
            }
            <div class="comments-list">
                <div class="loading">Loading comments...</div>
            </div>
        `);

    // Mock comments - in real implementation, load from API
    setTimeout(() => {
      const mockComments = [
        {
          id: 1,
          author: "tech_enthusiast",
          content:
            "Great post! This really helped me understand the concept better.",
          time: "2 hours ago",
          votes: 5,
        },
      ];

      const commentsHtml = mockComments
        .map(
          (comment) => `
                <div class="comment">
                    <div class="comment-meta">
                        <span class="comment-author">u/${comment.author}</span>
                        <span class="comment-time">${comment.time}</span>
                        <span class="comment-votes">${
                          comment.votes
                        } points</span>
                    </div>
                    <div class="comment-content">${escapeHtml(
                      comment.content
                    )}</div>
                </div>
            `
        )
        .join("");

      container.find(".comments-list").html(commentsHtml);
    }, 500);
  }

  /**
   * Initialize features
   */
  function initializeVoting() {
    if (!AICommunity.settings.enable_voting) {
      $(".post-voting").hide();
    }
  }

  function initializeComments() {
    if (!AICommunity.settings.enable_comments) {
      $('.post-actions .action-btn[data-action="toggle-comments"]').hide();
    }
  }

  function initializeSearch() {
    // Add search suggestions
    $("#search-input").on("focus", function () {
      // Show search suggestions
    });
  }

  function initializeMobileMenu() {
    // Close mobile menu when clicking outside
    $(document).on("click", function (e) {
      if (!$(e.target).closest(".mobile-menu, .mobile-menu-toggle").length) {
        if (AICommunity.showMobileMenu) {
          toggleMobileMenu();
        }
      }
    });
  }

  function initializeEditor() {
    if (
      AICommunity.settings.enable_rich_editor &&
      typeof wp !== "undefined" &&
      wp.editor
    ) {
      wp.editor.initialize("post-content", {
        tinymce: {
          wpautop: true,
          plugins:
            "charmap colorpicker hr lists paste tabfocus textcolor fullscreen wordpress wpautoresize wpeditimage wpemoji wpgallery wplink wptextpattern",
          toolbar1:
            "formatselect,bold,italic,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,wp_more,spellchecker,fullscreen,wp_adv",
          toolbar2:
            "strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help",
        },
        quicktags: true,
        mediaButtons: true,
      });
    }
  }

  // Utility Functions

  function toggleMobileMenu() {
    AICommunity.showMobileMenu = !AICommunity.showMobileMenu;
    $(".mobile-menu").toggleClass("show", AICommunity.showMobileMenu);
    $("body").toggleClass("mobile-menu-open", AICommunity.showMobileMenu);
  }

  function closeModals() {
    $(".modal").removeClass("show");
    $("body").removeClass("modal-open");
  }

  function showLoadingSpinner() {
    $(".loading-overlay").addClass("show");
  }

  function hideLoadingSpinner() {
    $(".loading-overlay").removeClass("show");
  }

  function showNotification(message, type = "info") {
    const notification = $(`
            <div class="notification notification-${type}">
                <div class="notification-content">
                    <span class="notification-message">${message}</span>
                    <button class="notification-close">&times;</button>
                </div>
            </div>
        `);

    $("body").append(notification);

    setTimeout(() => {
      notification.addClass("show");
    }, 10);

    setTimeout(() => {
      notification.removeClass("show");
      setTimeout(() => notification.remove(), 300);
    }, 5000);

    notification.find(".notification-close").on("click", () => {
      notification.removeClass("show");
      setTimeout(() => notification.remove(), 300);
    });
  }

  function getUserInitials(name) {
    return name
      .split(" ")
      .map((n) => n[0])
      .join("")
      .toUpperCase()
      .substring(0, 2);
  }

  function formatTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return "just now";
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;

    return date.toLocaleDateString();
  }

  function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + "M";
    if (num >= 1000) return (num / 1000).toFixed(1) + "k";
    return num.toString();
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }

  function getIcon(name) {
    const icons = {
      "chevron-up":
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18,15 12,9 6,15"/></svg>',
      "chevron-down":
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6,9 12,15 18,9"/></svg>',
      "message-circle":
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>',
      share:
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>',
      bookmark:
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>',
      bot: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2" ry="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>',
      "trending-up":
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23,6 13.5,15.5 8.5,10.5 1,18"/><polyline points="17,6 23,6 23,12"/></svg>',
      clock:
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
      star: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26 12,2"/></svg>',
      "arrow-up":
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5,12 12,5 19,12"/></svg>',
      "arrow-left":
        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12,19 5,12 12,5"/></svg>',
      send: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22,2 15,22 11,13 2,9 22,2"/></svg>',
    };
    return icons[name] || "";
  }

  // Global function for adding comments (called from inline onclick)
  window.addComment = function (postId) {
    const container = $(`#comments-${postId}`);
    const textarea = container.find("textarea");
    const content = textarea.val().trim();

    if (!content) return;

    if (!AICommunity.currentUser) {
      showNotification("Please login to comment", "warning");
      return;
    }

    // Add comment optimistically
    const commentHtml = `
            <div class="comment">
                <div class="comment-meta">
                    <span class="comment-author">u/${
                      AICommunity.currentUser.username
                    }</span>
                    <span class="comment-time">just now</span>
                    <span class="comment-votes">1 point</span>
                </div>
                <div class="comment-content">${escapeHtml(content)}</div>
            </div>
        `;

    container.find(".comments-list").append(commentHtml);
    textarea.val("");

    // Send to server
    $.ajax({
      url: aiCommunityData.restUrl + "comments",
      method: "POST",
      data: {
        post_id: postId,
        content: content,
      },
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", aiCommunityData.restNonce);
      },
    })
      .done(function () {
        showNotification("Comment added successfully!", "success");
      })
      .fail(function () {
        showNotification("Failed to add comment", "error");
      });
  };

  // Handle browser back/forward
  window.addEventListener("popstate", function (event) {
    if (event.state && event.state.page) {
      AICommunity.currentPage = event.state.page;
      renderApp();
    }
  });

  // Initialize page based on URL hash
  $(document).ready(function () {
    const hash = window.location.hash.substring(1);
    if (hash && ["login", "register", "create-post"].includes(hash)) {
      AICommunity.currentPage = hash;
    }
  });
})(jQuery);
