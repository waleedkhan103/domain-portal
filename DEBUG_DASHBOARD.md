**Debug Dashboard API Issue - Browser Steps**

## Step 1: Open Browser Developer Tools

Press `F12` and go to the **Console** tab.

## Step 2: Check if BASE_PATH is set

Copy and paste this in the console:

```javascript
console.log("BASE_PATH:", window.BASE_PATH);
```

Make sure it shows something like `/domain-portal` and NOT empty or `undefined`.

## Step 3: Check the actual API URL

```javascript
const dataUrl =
  (typeof BASE_PATH !== "undefined" ? BASE_PATH : "").replace(/\/$/, "") +
  "/admin/api/dashboard_data.php";
console.log("API URL:", dataUrl);
```

This should show the full URL like: `/domain-portal/admin/api/dashboard_data.php`

## Step 4: Check what the API returns

```javascript
fetch(dataUrl, { credentials: "same-origin" })
  .then((r) => {
    console.log("Response Status:", r.status);
    console.log("Response OK:", r.ok);
    return r.json();
  })
  .then((j) => {
    console.log("API Response:", j);
    if (j.success) {
      console.log("✓ Has data:", j.data.total_domains, "domains");
    }
  })
  .catch((err) => console.error("Fetch Error:", err));
```

## What you should see:

- Response Status: `200`
- Response OK: `true`
- API Response: `{success: true, data: {...}}`
- Has data: `1247 domains`

## If you see different results:

**If Status is 401:**

- You're not logged in
- Go to `/admin/login.php` first
- Login with: admin / admin
- Then return to dashboard

**If URL is wrong:**

- Check if BASE_PATH is set correctly
- If empty, refresh the page
- Check browser's view source to see if this line exists:
  ```html
  <script>
    window.BASE_PATH = "/domain-portal";
  </script>
  ```

**If response is an error:**

- Copy the exact error message
- Screenshot or send the full response

---

Let me know what you see and I can fix it!
