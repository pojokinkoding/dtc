import sys
import json
import joblib
import os
import warnings
import numpy as np
from sklearn.linear_model import LinearRegression
from sklearn.preprocessing import PolynomialFeatures
from sklearn.pipeline import make_pipeline

warnings.filterwarnings('ignore')

def train_model(spec_id, zst_history):
    """
    Train a polynomial regression model using historical ZST values.
    zst_history: list of dicts [{month_index: 1, zst: 4.2}, ...]
    month_index is continuous (e.g., 1=Jan2024, 13=Jan2025, etc.)
    """
    # Clean data by removing items with None or non-numeric values
    cleaned_history = []
    for d in zst_history:
        if d.get('month_index') is not None and d.get('zst') is not None and d.get('zlt') is not None:
            try:
                cleaned_history.append({
                    'month_index': float(d['month_index']),
                    'zst': float(d['zst']),
                    'zlt': float(d['zlt'])
                })
            except (ValueError, TypeError):
                pass
    zst_history = cleaned_history

    if len(zst_history) < 2:
        return None, "Not enough data to train (minimum 2 months needed)"

    X = np.array([d['month_index'] for d in zst_history]).reshape(-1, 1)
    y_zst = np.array([d['zst'] for d in zst_history])
    y_zlt = np.array([d['zlt'] for d in zst_history])

    # Use degree=2 polynomial for smoother trend, fallback to degree=1 if too few points
    degree = 2 if len(zst_history) >= 4 else 1
    model_zst = make_pipeline(PolynomialFeatures(degree), LinearRegression())
    model_zlt = make_pipeline(PolynomialFeatures(degree), LinearRegression())

    model_zst.fit(X, y_zst)
    model_zlt.fit(X, y_zlt)

    base_dir = os.path.dirname(os.path.abspath(__file__))
    models_dir = os.path.join(base_dir, 'models')
    os.makedirs(models_dir, exist_ok=True)

    path_zst = os.path.join(models_dir, f'zst_model_{spec_id}.joblib')
    path_zlt = os.path.join(models_dir, f'zlt_model_{spec_id}.joblib')
    
    joblib.dump(model_zst, path_zst)
    joblib.dump(model_zlt, path_zlt)

    return {"zst_path": path_zst, "zlt_path": path_zlt, "degree": degree, "data_points": len(zst_history)}, None

if __name__ == "__main__":
    try:
        if len(sys.argv) > 1:
            arg = sys.argv[1]
            # If arg is a file path, read from file (avoids Windows shell escaping)
            if os.path.isfile(arg):
                with open(arg, 'r', encoding='utf-8') as f:
                    input_data = json.load(f)
            else:
                input_data = json.loads(arg)
        else:
            input_data = json.load(sys.stdin)

        spec_id = input_data.get('spec_id')
        history = input_data.get('history', [])

        result, error = train_model(spec_id, history)

        if error:
            print(json.dumps({"status": "error", "message": error}))
            sys.exit(1)

        print(json.dumps({
            "status": "success",
            "spec_id": spec_id,
            "model_info": result
        }))

    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))
        sys.exit(1)
