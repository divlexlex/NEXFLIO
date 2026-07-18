import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../services/auth_service.dart';
import '../../services/api_service.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/auth_transition_screen.dart';
import '../home/home_screen.dart';

class SignupScreen extends StatefulWidget {
  const SignupScreen({super.key});

  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _confirmPasswordController =
      TextEditingController();

  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _isLoading = false;

  Future<void> _handleRegister() async {
    if (_passwordController.text != _confirmPasswordController.text) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text("Passwords don't match.")),
      );
      return;
    }

    setState(() => _isLoading = true);
    try {
      await AuthService.instance.register(
        name: _nameController.text.trim(),
        email: _emailController.text.trim(),
        password: _passwordController.text,
        passwordConfirmation: _confirmPasswordController.text,
      );
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        fadeSlideRoute(
          AuthTransitionScreen(
            message: 'Account created!',
            icon: Icons.check_circle,
            nextScreen: const HomeScreen(),
          ),
        ),
        (route) => false,
      );
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException ? e.message : 'Registration failed. Please try again.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: kTextColor),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(kDefaultPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // HEADER
              Text(
                "Create Account",
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                  color: kTextColor,
                ),
              ),
              const SizedBox(height: 10),
              Text(
                "Sign up to start booking your\npremium skin care treatments.",
                style: TextStyle(fontSize: 14, color: kTextColor, height: 1.4),
              ),
              const SizedBox(height: 30),

              // FULL NAME FIELD
              _buildInputLabel("Full Name"),
              _buildTextField(
                controller: _nameController,
                hintText: "Enter your full name",
                icon: Icons.person_outline,
              ),
              const SizedBox(height: 20),

              // EMAIL FIELD
              _buildInputLabel("Email Address"),
              _buildTextField(
                controller: _emailController,
                hintText: "Enter your email address",
                icon: Icons.email_outlined,
                keyboardType: TextInputType.emailAddress,
              ),
              const SizedBox(height: 20),

              // PASSWORD FIELD
              _buildInputLabel("Password"),
              _buildPasswordField(
                controller: _passwordController,
                hintText: "Create a password",
                isObscure: _obscurePassword,
                onToggle: () {
                  setState(() {
                    _obscurePassword = !_obscurePassword;
                  });
                },
              ),
              const SizedBox(height: 20),

              // CONFIRM PASSWORD FIELD
              _buildInputLabel("Confirm Password"),
              _buildPasswordField(
                controller: _confirmPasswordController,
                hintText: "Re-enter your password",
                isObscure: _obscureConfirmPassword,
                onToggle: () {
                  setState(() {
                    _obscureConfirmPassword = !_obscureConfirmPassword;
                  });
                },
              ),
              const SizedBox(height: 40),

              // REGISTER BUTTON
              SizedBox(
                width: double.infinity,
                height: 55,
                child: ElevatedButton(
                  onPressed: _isLoading ? null : _handleRegister,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kAccentColor,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    elevation: 0,
                  ),
                  child: _isLoading
                      ? const SizedBox(
                          width: 22,
                          height: 22,
                          child: CircularProgressIndicator(
                            color: Colors.white,
                            strokeWidth: 2.5,
                          ),
                        )
                      : const Text(
                          "Register",
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                ),
              ),
              const SizedBox(height: 20),

              // ALREADY HAVE AN ACCOUNT LOGIN LINK
              Center(
                child: GestureDetector(
                  onTap: () =>
                      Navigator.pop(context), // Pops back to Login Screen
                  child: RichText(
                    text: TextSpan(
                      text: "Already have an account? ",
                      style: TextStyle(color: kTextColor),
                      children: [
                        TextSpan(
                          text: "Login",
                          style: TextStyle(
                            color: kPrimaryColor,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 20),
            ],
          ),
        ),
      ),
    );
  }

  // ===== HELPER WIDGETS FOR CLEANER CODE =====

  Widget _buildInputLabel(String label) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8.0),
      child: Text(
        label,
        style: TextStyle(color: kTextColor, fontWeight: FontWeight.w500),
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String hintText,
    required IconData icon,
    TextInputType keyboardType = TextInputType.text,
  }) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      decoration: InputDecoration(
        filled: true,
        fillColor: kCardColor,
        hintText: hintText,
        hintStyle: TextStyle(color: kTextColor.withOpacity(0.5)),
        prefixIcon: Icon(icon, color: kPrimaryColor),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: kSecondaryColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: kSecondaryColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: kPrimaryColor, width: 2),
        ),
      ),
    );
  }

  Widget _buildPasswordField({
    required TextEditingController controller,
    required String hintText,
    required bool isObscure,
    required VoidCallback onToggle,
  }) {
    return TextField(
      controller: controller,
      obscureText: isObscure,
      decoration: InputDecoration(
        filled: true,
        fillColor: kCardColor,
        hintText: hintText,
        hintStyle: TextStyle(color: kTextColor.withOpacity(0.5)),
        prefixIcon: const Icon(Icons.lock_outline, color: kPrimaryColor),
        suffixIcon: IconButton(
          icon: Icon(
            isObscure ? Icons.visibility_off : Icons.visibility,
            color: kPrimaryColor,
          ),
          onPressed: onToggle,
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: kSecondaryColor),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: kSecondaryColor),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: kPrimaryColor, width: 2),
        ),
      ),
    );
  }
}
