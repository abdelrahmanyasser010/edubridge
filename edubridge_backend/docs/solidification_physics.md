# Physical Constraints and Mathematical Modeling of Ternary Directional Solidification

This document details the mathematical model, governing physical equations, boundary conditions, and numerical formulation for the **Horizontal Directional Solidification** of a ternary metallic alloy (e.g., Al-Cu-Mg or a generic ternary system A-B-C, where A is the solvent, and B and C are the solute elements).

---

## 1. Governing Equations

The solidification of a ternary system is a multi-scale, multi-physics phenomenon coupling heat transfer, solute transport, fluid dynamics, and phase transformation kinetics.

### 1.1 Heat Transfer with Phase Change (Enthalpy-Porosity Formulation)
The conservation of energy in the system, considering advective heat transport by the fluid phase and latent heat release during solidification, is expressed as:

$$\rho c_p \left( \frac{\partial T}{\partial t} + \mathbf{u} \cdot \nabla T \right) = \nabla \cdot (k \nabla T) + \rho L \frac{\partial f_s}{\partial t}$$

Where:
*   $\rho$ is the average density of the alloy ($kg/m^3$), assumed constant (Boussinesq approximation for buoyancy).
*   $c_p$ is the specific heat capacity ($J/(kg \cdot K)$).
*   $T$ is the temperature ($K$).
*   $\mathbf{u}$ is the fluid velocity vector ($m/s$).
*   $k$ is the thermal conductivity ($W/(m \cdot K)$), often calculated as a mixture rule: $k = f_s k_s + (1 - f_s) k_l$.
*   $L$ is the latent heat of fusion ($J/kg$).
*   $f_s$ is the solid fraction ($0 \le f_s \le 1$).

---

### 1.2 Solute Transport (Ternary System)
For a ternary system, we must track two independent solute concentrations: $C_B$ (solute B) and $C_C$ (solute C). Assuming no diffusion in the solid phase (Scheil-type assumption) or limited diffusion, the transport of species in the liquid phase is governed by:

$$\frac{\partial C_i}{\partial t} + \nabla \cdot \left( \mathbf{u} C_{l,i} \right) = \nabla \cdot \left( D_{l,i} f_l \nabla C_{l,i} \right) + (1 - k_i) C_{l,i} \frac{\partial f_s}{\partial t} \quad \text{for } i \in \{B, C\}$$

Where:
*   $C_i$ is the average local concentration of species $i$ ($wt.\%$).
*   $C_{l,i}$ is the concentration of species $i$ in the liquid phase ($wt.\%$).
*   $f_l = 1 - f_s$ is the liquid fraction.
*   $D_{l,i}$ is the diffusion coefficient of species $i$ in the liquid ($m^2/s$).
*   $k_i$ is the partition coefficient of species $i$ between solid and liquid ($k_i = C_{s,i} / C_{l,i}$).

Under the assumption of local thermodynamic equilibrium at the solid-liquid interface, the average concentration is related to the liquid concentration by:
$$C_i = f_s C_{s,i} + (1 - f_s) C_{l,i} = [1 - (1 - k_i)f_s] C_{l,i}$$

---

### 1.3 Momentum Transport (Fluid Flow in Porous Media)
The fluid flow in the mushy (semi-solid) zone is modeled using the Brinkman-extended Darcy-Forchheimer equation, which seamlessly transitions from the Navier-Stokes equations in the pure liquid region ($f_s = 0$) to Darcy flow in the porous mushy region:

$$\rho_0 \left( \frac{\partial \mathbf{u}}{\partial t} + (\mathbf{u} \cdot \nabla) \mathbf{u} \right) = -\nabla p + \mu \nabla^2 \mathbf{u} + \mathbf{F}_b - S_d \mathbf{u}$$

Where:
*   $p$ is the pressure ($Pa$).
*   $\mu$ is the dynamic viscosity of the melt ($Pa \cdot s$).
*   $\mathbf{F}_b$ is the buoyancy force term.
*   $S_d$ is the Darcy drag term (permeability resistance).

#### 1.3.1 Buoyancy Force (Boussinesq Approximation)
Buoyancy is driven by both temperature and solutal gradients (thermosolutal convection):

$$\mathbf{F}_b = \rho_0 \mathbf{g} \left[ \beta_T (T - T_{ref}) + \beta_B (C_{l,B} - C_{B,0}) + \beta_C (C_{l,C} - C_{C,0}) \right]$$

Where:
*   $\mathbf{g}$ is the acceleration due to gravity ($m/s^2$).
*   $\beta_T$ is the thermal expansion coefficient ($1/K$).
*   $\beta_B, \beta_C$ are the solutal expansion coefficients for species B and C ($1/wt.\%$).
*   $T_{ref}, C_{B,0}, C_{C,0}$ are reference values.

#### 1.3.2 Permeability in the Mushy Zone (Kozeny-Carman Relation)
The drag coefficient $S_d = \frac{\mu}{K}$ is determined by the isotropic permeability $K$ ($m^2$) of the dendritic network:

$$K = K_0 \frac{(1 - f_s)^3}{f_s^2 + \epsilon}$$

Where:
*   $K_0$ is a constant related to the primary dendritic arm spacing ($\lambda_1$), typically $K_0 \approx 6 \times 10^{-4} \lambda_1^2$.
*   $\epsilon$ is a small parameter ($10^{-5}$) to prevent division by zero in the fully liquid region ($f_s = 0$).

---

### 1.4 Phase Diagram and Thermodynamic Relations
For a dilute ternary system, the liquidus temperature $T_L$ depends linearly on the solute concentrations:

$$T_L = T_m + m_B C_{l,B} + m_C C_{l,C}$$

Where:
*   $T_m$ is the melting point of the pure solvent (A) ($K$).
*   $m_B, m_C$ are the liquidus slope constants ($K/wt.\%$), which are typically negative.

The local undercooling $\Delta T$ that drives solidification is:
$$\Delta T = T_L(C_{l,B}, C_{l,C}) - T$$

---

### 1.5 Nucleation and Grain Growth Kinetics
To couple the macro-scale heat/solute transfer with the micro-scale structure, we track the grain density $n$ and the grain radius $R$.

#### 1.5.1 Heterogeneous Nucleation (Gaussian Distribution)
The continuous nucleation model of Rappaz and Thévoz expresses the evolution of grain density $n$ (number of grains per unit volume) as:

$$\frac{\partial n}{\partial t} = \frac{N_{max}}{\sqrt{2\pi}\Delta T_{\sigma}} \exp\left( -\frac{(\Delta T - \Delta T_m)^2}{2\Delta T_{\sigma}^2} \right) \frac{\partial(\Delta T)}{\partial t}$$

Where:
*   $N_{max}$ is the maximum density of active nucleating substrates ($m^{-3}$).
*   $\Delta T_m$ is the mean undercooling for nucleation ($K$).
*   $\Delta T_{\sigma}$ is the standard deviation of the nucleation distribution ($K$).
*   This rate is non-zero only when the undercooling is increasing ($\frac{\partial(\Delta T)}{\partial t} > 0$).

#### 1.5.2 Grain Growth
The growth rate of the grain envelope $v_g = \frac{dR}{dt}$ is related to the local undercooling by the Kurz-Giovanola-Trivedi (KGT) model or a simplified power-law approximation:

$$\frac{\partial R}{\partial t} = \mu_g (\Delta T)^2$$

Where $\mu_g$ is the growth kinetic coefficient ($m/(s \cdot K^2)$). The solid fraction $f_s$ can then be related to the grain density and radius by:
$$f_s(t) = 1 - \exp\left( -\frac{4}{3}\pi n(t) R(t)^3 \right)$$
which accounts for grain impingement via the Avrami equation.

---

## 2. Boundary and Initial Conditions

For a 1D horizontal directional solidification setup of length $L_x$ (from $x=0$ at the chill wall to $x=L_x$ at the hot end):

### 2.1 Thermal Boundary Conditions
*   **At the chill wall ($x = 0$):**
    $$-k \frac{\partial T}{\partial x} = h_c (T - T_{cool})$$
    where $h_c$ is the heat transfer coefficient ($W/(m^2 \cdot K)$) and $T_{cool}$ is the coolant temperature.
*   **At the hot end ($x = L_x$):**
    $$-k \frac{\partial T}{\partial x} = q_{ext}$$
    or a prescribed temperature boundary $T(L_x, t) = T_{hot}(t)$.

### 2.2 Solute Boundary Conditions
No solute can cross the physical boundaries:
$$\left. \frac{\partial C_{l,i}}{\partial x} \right|_{x=0} = 0, \quad \left. \frac{\partial C_{l,i}}{\partial x} \right|_{x=L_x} = 0 \quad (i \in \{B, C\})$$

### 2.3 Fluid Flow Boundary Conditions
No-slip boundaries at the walls:
$$u(0, t) = 0, \quad u(L_x, t) = 0$$

### 2.4 Initial Conditions
At $t = 0$, the system is fully liquid at a uniform superheated temperature $T_0 > T_L(C_{B,0}, C_{C,0})$:
$$T(x, 0) = T_0, \quad C_B(x, 0) = C_{B,0}, \quad C_C(x, 0) = C_{C,0}, \quad u(x, 0) = 0, \quad f_s(x, 0) = 0, \quad n(x, 0) = 0$$

---

## 3. Physical Parameters Table (Example: Al-Cu-Mg System)

| Parameter | Symbol | Value | Unit |
| :--- | :--- | :--- | :--- |
| Density | $\rho$ | $2500$ | $kg/m^3$ |
| Latent Heat | $L$ | $3.9 \times 10^5$ | $J/kg$ |
| Specific Heat | $c_p$ | $900$ | $J/(kg \cdot K)$ |
| Thermal Conductivity (liquid) | $k_l$ | $90$ | $W/(m \cdot K)$ |
| Thermal Conductivity (solid) | $k_s$ | $150$ | $W/(m \cdot K)$ |
| Dynamic Viscosity | $\mu$ | $1.3 \times 10^{-3}$ | $Pa \cdot s$ |
| Melting Point of Pure Al | $T_m$ | $933.5$ | $K$ |
| Partition Coefficient of Cu | $k_B$ | $0.17$ | $-$ |
| Partition Coefficient of Mg | $k_C$ | $0.48$ | $-$ |
| Liquidus Slope of Cu | $m_B$ | $-3.4$ | $K/wt.\%$ |
| Liquidus Slope of Mg | $m_C$ | $-6.2$ | $K/wt.\%$ |
| Liquid Diffusion of Cu | $D_{l,B}$ | $3.0 \times 10^{-9}$ | $m^2/s$ |
| Liquid Diffusion of Mg | $D_{l,C}$ | $2.5 \times 10^{-9}$ | $m^2/s$ |
| Thermal Expansion Coeff. | $\beta_T$ | $1.2 \times 10^{-4}$ | $K^{-1}$ |
| Solutal Expansion of Cu | $\beta_B$ | $7.3 \times 10^{-3}$ | $(wt.\%)^{-1}$ |
| Solutal Expansion of Mg | $\beta_C$ | $-4.5 \times 10^{-3}$ | $(wt.\%)^{-1}$ |
| Growth Coefficient | $\mu_g$ | $1.5 \times 10^{-5}$ | $m/(s \cdot K^2)$ |
| Max Nucleation Density | $N_{max}$ | $1.0 \times 10^{11}$ | $m^{-3}$ |
| Mean Undercooling | $\Delta T_m$ | $2.0$ | $K$ |
| Nucleation Std Dev | $\Delta T_{\sigma}$ | $0.5$ | $K$ |
| Permeability Constant | $K_0$ | $5.0 \times 10^{-11}$ | $m^2$ |
