# Moodle Academy Auto Enrol

**Version:** 2.0  
**Author:** Alvaro Perez Blanco – Moodle  
**Tested with:** WordPress 6.6+, WooCommerce 8+, Moodle 5.1 (via REST Web Services)

---

## 🧩 Description

**Moodle Academy Auto Enrol** is a WordPress plugin that automatically enrols WooCommerce customers into Moodle courses using Moodle's **REST Web Services API**.

It supports:
- Automatic enrolment when an order is marked **completed** in WooCommerce.
- Per-product course overrides.
- Manual enrolment testing directly from the admin panel.
- Verbose logging with configurable rotation.
- Moodle 5.1-ready (form-encoded requests, improved error handling, retries, and detailed logging).

---

## ⚙️ Installation

1. **Download** the plugin folder or clone the repository into your WordPress plugins directory:
   ```bash
   wp-content/plugins/moodle-academy-auto-enrol/
   ```

2. Activate the plugin from your **WordPress Dashboard → Plugins** page.

3. Ensure that **WooCommerce** is active.

4. Verify that your Moodle site has **Web Services** enabled and a **token** with the following capabilities:
   - `core_user_get_users_by_field`
   - `core_user_create_users`
   - `enrol_manual_enrol_users`
   - `core_webservice_get_site_info`

---

## 🛠️ Configuration

After activation, navigate to:

**WordPress Admin → Moodle → Moodle Academy**

and fill in the following fields:

| Setting | Description |
|----------|--------------|
| **Moodle domain (base URL)** | Base URL of your Moodle installation. Example: `https://moodle.example.com/public` |
| **Moodle token** | Token generated in Moodle for a user with the necessary permissions. |
| **Global Course ID** | Default course ID for enrolment (can be overridden per product). |
| **Auth method (optional)** | Set to `manual` if you want to enforce manual authentication. Leave blank otherwise. |
| **Logging** | Enable or disable debug logging (default: enabled). Logs are saved to `wp-content/moodle-debug.log`. |
| **Max log size (bytes)** | Log rotation threshold (default: 1 MB). |

---

## 🧪 Admin Tools

From the Moodle Academy settings page, you can:

- **Clear Log:** Delete the existing log file.
- **Test Site Info:** Calls `core_webservice_get_site_info` to confirm connectivity with Moodle.
- **Manual Enrolment Test:** Enter a user’s name and email to manually trigger the enrolment process (useful for debugging).

---

## 🧮 Per-Product Course Mapping

You can override the global course ID per product:

1. Edit any WooCommerce product.
2. In the **Moodle Course Mapping** box (sidebar), enter a specific **Moodle Course ID**.
3. Save the product.

When that product is purchased, the user will be enrolled in the specified course instead of the global one.

---

## 🔄 How It Works

1. When an order reaches the **“Completed”** status, the plugin triggers Moodle enrolment.
2. It checks whether the customer already exists in Moodle (by email).
3. If not found, it creates the Moodle user (with `createpassword = 1`).
4. Finally, it enrols the user into the appropriate course (`student` role by default).
5. Logs are stored in `wp-content/moodle-debug.log` for debugging.

---

## 🧰 Logging and Debugging

The plugin writes verbose logs for every REST request, including:
- URLs (with tokens)
- Request bodies
- Response codes and headers
- Response bodies

Logs rotate automatically when the file exceeds the configured size.

**To clear logs:** click **“Clear Log”** in the admin settings.  
**To disable logs:** uncheck the “Enable debug logging” option.

---

## 🧩 Moodle Web Services Reference

The plugin uses the following Moodle API endpoints:

| Moodle Function | Purpose |
|------------------|----------|
| `core_webservice_get_site_info` | Tests API connectivity |
| `core_user_get_users_by_field` | Finds existing users by email |
| `core_user_create_users` | Creates a new Moodle user |
| `enrol_manual_enrol_users` | Enrols a user into a specific course |

---

## 🧑‍💻 Developer Notes

- All requests use **form-encoded** format (`application/x-www-form-urlencoded`), compatible with Moodle 5.1+.
- Each request includes detailed retry logic (`wp_remote_post` with exponential delay).
- You can manually trigger enrolment via:
  ```php
  mae_enrol_in_moodle($firstname, $lastname, $email, $order_id = 0, $course_override = 0);
  ```
- Moodle API tokens and URLs are sanitized and validated via WordPress `register_setting()` API.

---

## 🧾 Changelog

### v2.0
- Moodle 5.1 compatibility (form-encoded requests).
- Full retry logic for failed API calls.
- Verbose and rotatable logging system.
- Admin interface for manual enrolment and testing.
- Per-product course mapping meta box.
- Clear log and site info test tools.

---

## ⚖️ License

This project is licensed under the **GNU General Public License v2.0** or later.  
See the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

---

## 💬 Credits

Developed by **Alvaro Perez Blanco** for Moodle Academy.  
Special thanks to the Moodle HQ and WordPress developer communities.
