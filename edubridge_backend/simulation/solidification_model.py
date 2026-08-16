import os
import numpy as np
import matplotlib.pyplot as plt

# -----------------------------------------------------------------------------
# 1. PHYSICAL PARAMETERS (Al-Cu-Mg Ternary System)
# -----------------------------------------------------------------------------
# Thermal & Physical Properties
RHO = 2500.0          # Density (kg/m^3)
L_FUSION = 3.9e5      # Latent heat of fusion (J/kg)
CP = 900.0            # Specific heat capacity (J/kg*K)
K_L = 90.0            # Thermal conductivity of liquid (W/m*K)
K_S = 150.0           # Thermal conductivity of solid (W/m*K)
MU = 1.3e-3           # Dynamic viscosity (Pa*s)
G = 9.81              # Acceleration due to gravity (m/s^2)

# Phase Diagram Properties (Solute B: Cu, Solute C: Mg)
T_M = 933.5           # Melting point of pure Al (K)
K_B = 0.17            # Partition coefficient for Cu
K_C = 0.48            # Partition coefficient for Mg
M_B = -3.4            # Liquidus slope of Cu (K/wt.%)
M_C = -6.2            # Liquidus slope of Mg (K/wt.%)
D_B = 3.0e-9          # Solute diffusion coefficient of Cu in liquid (m^2/s)
D_C = 2.5e-9          # Solute diffusion coefficient of Mg in liquid (m^2/s)

# Thermosolutal Buoyancy Coefficients
BETA_T = 1.2e-4       # Thermal expansion coefficient (1/K)
BETA_B = 7.3e-3       # Solutal expansion coefficient of Cu (1/wt.%)
BETA_C = -4.5e-3      # Solutal expansion coefficient of Mg (1/wt.%)
T_REF = 933.5         # Reference temperature (K)

# Nucleation & Growth Kinetics
N_MAX = 1.0e11        # Maximum nucleation site density (m^-3)
DT_M = 2.0            # Mean undercooling for nucleation (K)
DT_SIGMA = 0.5        # Standard deviation of nucleation (K)
MU_G = 1.5e-5         # Growth kinetic coefficient (m/(s*K^2))
K0 = 5.0e-11          # Permeability constant in mushy zone (m^2)
EPSILON = 1e-6        # Small number to prevent division by zero in permeability

# -----------------------------------------------------------------------------
# 2. NUMERICAL GRID AND SIMULATION SETUP
# -----------------------------------------------------------------------------
NX = 100              # Number of spatial grid points
LX = 0.1              # Length of the domain (m) (10 cm)
DX = LX / (NX - 1)    # Spatial step (m)
DT = 0.001             # Time step (s) (stable for DX=0.00101 and alpha=6.67e-5)
STEPS = 5000          # Number of time steps to simulate
PLOT_EVERY = 1000     # Plot interval

# Initial Conditions
T_0 = 950.0           # Initial temperature (superheated liquid) (K)
C_B0 = 4.5            # Initial Cu concentration (wt.%)
C_C0 = 1.5            # Initial Mg concentration (wt.%)
T_COOL = 300.0        # Coolant temperature (K)
H_C = 500.0           # Heat transfer coefficient at chill wall (W/m^2*K)

# Initialize Arrays
x = np.linspace(0, LX, NX)
T = np.ones(NX) * T_0
C_B = np.ones(NX) * C_B0
C_C = np.ones(NX) * C_C0
f_s = np.zeros(NX)
n_grains = np.zeros(NX)
R_grains = np.zeros(NX)
u = np.zeros(NX)
max_undercooling = np.zeros(NX)

# Record lists for visualization
history_T = []
history_C_B = []
history_C_C = []
history_fs = []
history_u = []
history_times = []

# -----------------------------------------------------------------------------
# 3. HELPER FUNCTIONS
# -----------------------------------------------------------------------------
def get_permeability(fs):
    """Calculate local permeability using the Kozeny-Carman relation."""
    return K0 * ((1.0 - fs) ** 3) / (fs ** 2 + EPSILON)

def get_liquidus_temp(cb_liq, cc_liq):
    """Calculate liquidus temperature for the ternary system."""
    return T_M + M_B * cb_liq + M_C * cc_liq

def gaussian_nucleation_rate(delta_T):
    """Calculate the nucleation rate using a Gaussian distribution of undercooling."""
    if delta_T <= 0:
        return 0.0
    factor = N_MAX / (DT_SIGMA * np.sqrt(2 * np.pi))
    exponent = -0.5 * ((delta_T - DT_M) / DT_SIGMA) ** 2
    return factor * np.exp(exponent)

# -----------------------------------------------------------------------------
# 4. SIMULATION LOOP
# -----------------------------------------------------------------------------
print("Starting simulation of ternary directional solidification...")

for step in range(STEPS):
    t = step * DT
    
    # Store old values for explicit update scheme
    T_old = T.copy()
    C_B_old = C_B.copy()
    C_C_old = C_C.copy()
    f_s_old = f_s.copy()
    
    # Calculate liquid concentrations assuming local equilibrium
    # C_avg = f_s * C_solid + (1 - f_s) * C_liquid = [1 - (1 - k)*f_s] * C_liquid
    # => C_liquid = C_avg / [1 - (1 - k)*f_s]
    C_B_liq = C_B_old / (1.0 - (1.0 - K_B) * f_s_old)
    C_C_liq = C_C_old / (1.0 - (1.0 - K_C) * f_s_old)
    
    # 1. Update Fluid Velocity (Local Darcy-Buoyancy equilibrium in 1D)
    # The fluid velocity is driven by local buoyancy forces and restricted by permeability
    for i in range(1, NX - 1):
        buoyancy = RHO * G * (BETA_T * (T_old[i] - T_REF) + 
                              BETA_B * (C_B_liq[i] - C_B0) + 
                              BETA_C * (C_C_liq[i] - C_C0))
        K = get_permeability(f_s_old[i])
        # Darcy velocity scale u = K/mu * F_buoyancy
        u[i] = (K / MU) * buoyancy
    u[0] = 0.0
    u[-1] = 0.0

    # 2. Update Phase Change, Nucleation, and Grain Growth
    for i in range(NX):
        T_L = get_liquidus_temp(C_B_liq[i], C_C_liq[i])
        delta_T = max(0.0, T_L - T_old[i])
        
        # Track max undercooling to handle nucleation rate strictly for increasing undercooling
        if delta_T > max_undercooling[i]:
            dn = gaussian_nucleation_rate(delta_T) * (delta_T - max_undercooling[i])
            n_grains[i] += max(0.0, dn)
            max_undercooling[i] = delta_T
        
        # Grain growth rate
        v_g = MU_G * (delta_T ** 2)
        R_grains[i] += v_g * DT
        
        # Avrami model for solid fraction from grains
        if n_grains[i] > 0:
            volume_fraction = (4.0/3.0) * np.pi * n_grains[i] * (R_grains[i] ** 3)
            f_s[i] = 1.0 - np.exp(-volume_fraction)
            f_s[i] = min(1.0, max(0.0, f_s[i]))
            
            # Simple Scheil fallback if micro-kinetics are inactive/slow
            # limit solid fraction at eutectic limit if relevant
            f_s[i] = max(f_s[i], f_s_old[i])
            
    df_s = f_s - f_s_old

    # 3. Solve Heat Transfer Equation (FDM)
    for i in range(1, NX - 1):
        # Thermal conductivity based on phase fraction
        k_eff = f_s_old[i] * K_S + (1.0 - f_s_old[i]) * K_L
        alpha = k_eff / (RHO * CP)
        
        # Conduction term
        conduction = alpha * (T_old[i+1] - 2*T_old[i] + T_old[i-1]) / (DX ** 2)
        # Advection term (Upwind scheme for stability)
        if u[i] >= 0:
            advection = u[i] * (T_old[i] - T_old[i-1]) / DX
        else:
            advection = u[i] * (T_old[i+1] - T_old[i]) / DX
            
        # Latent heat source term
        latent_heat = (L_FUSION / CP) * df_s[i]
        
        T[i] = T_old[i] + DT * (conduction - advection) + latent_heat

    # Boundary Conditions for Heat
    # Left Boundary (Chill Wall with convective heat transfer)
    T[0] = T_old[0] + DT * ( (K_L/DX)*(T_old[1] - T_old[0]) - H_C*(T_old[0] - T_COOL) ) / (RHO * CP * DX)
    # Right Boundary (Adiabatic / Neumann)
    T[-1] = T[-2]

    # 4. Solve Solute Transport Equations (FDM)
    for i in range(1, NX - 1):
        # Solute B (Cu)
        diff_B = D_B * (1.0 - f_s_old[i]) * (C_B_liq[i+1] - 2*C_B_liq[i] + C_B_liq[i-1]) / (DX ** 2)
        if u[i] >= 0:
            adv_B = u[i] * (C_B_liq[i] - C_B_liq[i-1]) / DX
        else:
            adv_B = u[i] * (C_B_liq[i+1] - C_B_liq[i]) / DX
        C_B[i] = C_B_old[i] + DT * (diff_B - adv_B)

        # Solute C (Mg)
        diff_C = D_C * (1.0 - f_s_old[i]) * (C_C_liq[i+1] - 2*C_C_liq[i] + C_C_liq[i-1]) / (DX ** 2)
        if u[i] >= 0:
            adv_C = u[i] * (C_C_liq[i] - C_C_liq[i-1]) / DX
        else:
            adv_C = u[i] * (C_C_liq[i+1] - C_C_liq[i]) / DX
        C_C[i] = C_C_old[i] + DT * (diff_C - adv_C)

    # Boundary Conditions for Solute (Zero Flux / Neumann)
    C_B[0] = C_B[1]
    C_B[-1] = C_B[-2]
    C_C[0] = C_C[1]
    C_C[-1] = C_C[-2]

    # Save data periodically
    if step % PLOT_EVERY == 0:
        history_T.append(T.copy())
        history_C_B.append(C_B.copy())
        history_C_C.append(C_C.copy())
        history_fs.append(f_s.copy())
        history_u.append(u.copy())
        history_times.append(t)

# -----------------------------------------------------------------------------
# 5. DATA VISUALIZATION & PLOTTING
# -----------------------------------------------------------------------------
print("Generating simulation plots...")
fig, axs = plt.subplots(2, 2, figsize=(12, 10))

for idx, t_val in enumerate(history_times):
    label_str = f"t = {t_val:.1f}s"
    axs[0, 0].plot(x * 100, history_T[idx] - 273.15, label=label_str)
    axs[0, 1].plot(x * 100, history_fs[idx], label=label_str)
    axs[1, 0].plot(x * 100, history_C_B[idx], label=label_str)
    axs[1, 1].plot(x * 100, history_u[idx] * 1e6, label=label_str)  # scale to microns/sec

axs[0, 0].set_title("Temperature Profile")
axs[0, 0].set_xlabel("Distance from Chill (cm)")
axs[0, 0].set_ylabel("Temperature (°C)")
axs[0, 0].legend()
axs[0, 0].grid(True)

axs[0, 1].set_title("Solid Fraction")
axs[0, 1].set_xlabel("Distance from Chill (cm)")
axs[0, 1].set_ylabel("fs")
axs[0, 1].legend()
axs[0, 1].grid(True)

axs[1, 0].set_title("Solute Concentration (Cu)")
axs[1, 0].set_xlabel("Distance from Chill (cm)")
axs[1, 0].set_ylabel("Concentration (wt.%)")
axs[1, 0].legend()
axs[1, 0].grid(True)

axs[1, 1].set_title("Fluid Velocity (x-direction)")
axs[1, 1].set_xlabel("Distance from Chill (cm)")
axs[1, 1].set_ylabel("Velocity (µm/s)")
axs[1, 1].legend()
axs[1, 1].grid(True)

plt.tight_layout()
output_plot = os.path.join(os.path.dirname(__file__), "solidification_results.png")
plt.savefig(output_plot)
print(f"Simulation completed. Plot saved to {output_plot}")
