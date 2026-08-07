import sys
import json
import joblib
import os
import warnings
import numpy as np

warnings.filterwarnings('ignore')

def predict(spec_id, forecast_month_indices):
    """
    Load trained model for spec_id and forecast ZST/ZLT for given month indices.
    forecast_month_indices: list of ints (e.g., [13, 14, 15])
    """
    base_dir = os.path.dirname(os.path.abspath(__file__))
    models_dir = os.path.join(base_dir, 'models')

    path_zst = os.path.join(models_dir, f'zst_model_{spec_id}.joblib')
    path_zlt = os.path.join(models_dir, f'zlt_model_{spec_id}.joblib')

    if not os.path.exists(path_zst) or not os.path.exists(path_zlt):
        return None, f"Model not found for spec_id={spec_id}. Please train first."

    model_zst = joblib.load(path_zst)
    model_zlt = joblib.load(path_zlt)

    X = np.array(forecast_month_indices).reshape(-1, 1)
    pred_zst = model_zst.predict(X).tolist()
    pred_zlt = model_zlt.predict(X).tolist()

    return {
        "month_indices": forecast_month_indices,
        "zst_forecast": [round(v, 3) for v in pred_zst],
        "zlt_forecast": [round(v, 3) for v in pred_zlt]
    }, None

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
        forecast_indices = input_data.get('forecast_month_indices', [])

        result, error = predict(spec_id, forecast_indices)

        if error:
            print(json.dumps({"status": "error", "message": error}))
            sys.exit(1)

        print(json.dumps({"status": "success", "spec_id": spec_id, **result}))

    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))
        sys.exit(1)
